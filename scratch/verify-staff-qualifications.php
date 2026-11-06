<?php

/**
 * Verifies repeatable staff qualifications: create, edit prefill, replace,
 * per-row validation and display on the profile + staff portal.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Department;
use App\Models\Designation;
use App\Models\Role;
use App\Models\Staff;
use App\Models\StaffQualification;
use Illuminate\Support\Facades\DB;

const BASE = 'http://newschool.test';
const COOKIE = __DIR__.'/cookie-qual.jar';

$pass = 0;
$fail = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo sprintf("  [%s] %s%s\n", $ok ? 'OK  ' : 'FAIL', $label, $detail !== '' ? ' — '.$detail : '');
}
function req(string $method, string $path, array $form = []): array
{
    $ch = curl_init(BASE.$path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => COOKIE, CURLOPT_COOKIEFILE => COOKIE,
        CURLOPT_HTTPHEADER => ['Accept: text/html'], CURLOPT_TIMEOUT => 60,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
    }
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => (string) $body];
}
function token(string $html): ?string
{
    return preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m) ? $m[1] : null;
}

@unlink(COOKIE);
$login = req('GET', '/login');
req('POST', '/login', ['_token' => token($login['body']), 'email' => 'admin@edusphere.test', 'password' => 'Admin@12345']);

$role = Role::query()->whereNotIn('slug', ['student', 'parent', 'super-admin'])->firstOrFail();
$department = Department::query()->first();
$designation = Designation::query()->first();

echo "\n=== Form has repeatable rows ===\n";
$form = req('GET', '/staff/create');
check('create form loads', $form['status'] === 200, 'status '.$form['status']);
check('has add qualification button', str_contains($form['body'], 'Add qualification'));
check('has qualification component', str_contains($form['body'], 'staffQualifications('));
check('has exam field binding', str_contains($form['body'], '[exam_name]'));
check('has institution field binding', str_contains($form['body'], '[institution]'));
check('has passing year binding', str_contains($form['body'], '[passing_year]'));

echo "\n=== Create staff with two qualifications ===\n";
$payload = [
    '_token' => token($form['body']),
    'first_name' => 'Qual',
    'last_name' => 'Probe',
    'gender' => 'male',
    'phone' => '+8801711111111',
    'role_id' => $role->id,
    'department_id' => $department?->id,
    'designation_id' => $designation?->id,
    'joining_date' => now()->toDateString(),
    'employment_type' => 'full_time',
    'category' => 'academic',
    'status' => 'active',
    'basic_salary' => 10000,
    'qualifications' => [
        ['exam_name' => 'SSC', 'institution' => 'Rangpur School', 'passing_year' => 2024, 'result' => 'GPA 5.00'],
        ['exam_name' => 'HSC', 'institution' => 'Rangpur College', 'passing_year' => 2026, 'result' => 'GPA 4.83'],
    ],
];

$store = req('POST', '/staff', $payload);
check('staff created', in_array($store['status'], [302, 303], true), 'status '.$store['status']);

$staff = Staff::with('qualifications')->where('phone', '+8801711111111')->latest('id')->first();
check('staff record found', $staff !== null, $store['status'] === 302 ? 'redirect ok' : 'not created');

if ($staff) {
    check('two qualifications stored', $staff->qualifications->count() === 2, (string) $staff->qualifications->count());
    check('first row matches SSC / Rangpur School', $staff->qualifications[0]->exam_name === 'SSC' && $staff->qualifications[0]->institution === 'Rangpur School');
    check('year + result stored', (int) $staff->qualifications[0]->passing_year === 2024 && $staff->qualifications[0]->result === 'GPA 5.00');
    check('order preserved', $staff->qualifications->pluck('exam_name')->all() === ['SSC', 'HSC']);

    echo "\n=== Profile shows qualifications ===\n";
    $show = req('GET', '/staff/'.$staff->id);
    check('profile loads', $show['status'] === 200, 'status '.$show['status']);
    check('profile lists Rangpur School', str_contains($show['body'], 'Rangpur School'));
    check('profile shows year', str_contains($show['body'], '2026'));

    echo "\n=== Edit prefills rows ===\n";
    $edit = req('GET', '/staff/'.$staff->id.'/edit');
    check('edit loads', $edit['status'] === 200, 'status '.$edit['status']);
    check('existing rows prefilled', str_contains($edit['body'], 'Rangpur School') && str_contains($edit['body'], 'HSC'));

    echo "\n=== Update replaces qualification rows ===\n";
    $update = req('POST', '/staff/'.$staff->id, [
        '_token' => token($edit['body']),
        '_method' => 'PUT',
        'first_name' => 'Qual',
        'last_name' => 'Probe',
        'gender' => 'male',
        'phone' => '+8801711111111',
        'role_id' => $role->id,
        'department_id' => $department?->id,
        'designation_id' => $designation?->id,
        'joining_date' => now()->toDateString(),
        'employment_type' => 'full_time',
    'category' => 'academic',
        'status' => 'active',
        'basic_salary' => 10000,
        'qualifications' => [
            ['exam_name' => 'BSc', 'institution' => 'RU', 'passing_year' => 2026, 'result' => 'First class'],
        ],
    ]);
    check('update redirects', in_array($update['status'], [302, 303], true), 'status '.$update['status']);

    $staff->refresh()->load('qualifications');
    check('rows replaced', $staff->qualifications->count() === 1, (string) $staff->qualifications->count());
    check('new row stored', $staff->qualifications[0]->exam_name === 'BSc' && $staff->qualifications[0]->institution === 'RU');
    check('empty rows ignored', StaffQualification::where('staff_id', $staff->id)->where('exam_name', '')->count() === 0);

    echo "\n=== Cleanup ===\n";
    DB::table('staff_qualifications')->where('staff_id', $staff->id)->delete();
    DB::table('salary_structures')->where('staff_id', $staff->id)->delete();
    DB::table('staff')->where('id', $staff->id)->delete();
    DB::table('audit_logs')->where('description', 'like', '%Qual Probe%')->delete();
    echo "  removed test staff #{$staff->id}\n";
}

echo "\n=== RESULT: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
