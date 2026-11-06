<?php

/**
 * Verifies the staff category field (Academic / Non-academic): default,
 * backfill, required validation, filtering and display.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Department;
use App\Models\Designation;
use App\Models\Role;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

const BASE = 'http://newschool.test';
const COOKIE = __DIR__.'/cookie-cat.jar';

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

echo "\n=== Backfill / existing data ===\n";
$total = Staff::count();
$missing = Staff::whereNull('category')->count();
check('every staff row has a category', $missing === 0, "{$missing} missing of {$total}");

$academicCount = Staff::where('category', 'academic')->count();
$nonAcademicCount = Staff::where('category', 'non_academic')->count();
check('categories are valid values only', Staff::whereNotIn('category', ['academic', 'non_academic'])->count() === 0, "academic {$academicCount} / non-academic {$nonAcademicCount}");

// Teaching designations must be academic.
$teacherAcademic = Staff::whereHas('designation', fn ($q) => $q->where('name', 'like', '%Teacher%'))
    ->where('category', 'non_academic')->count();
check('teacher designations are academic', $teacherAcademic === 0, "{$teacherAcademic} mismatched");

echo "\n=== Form + validation ===\n";
$form = req('GET', '/staff/create');
check('form has category select', str_contains($form['body'], 'name="category"'));
check('form offers both options', str_contains($form['body'], 'Non-academic'));

$basePayload = [
    '_token' => token($form['body']),
    'first_name' => 'Category',
    'last_name' => 'Probe',
    'gender' => 'male',
    'phone' => '+8801788880001',
    'role_id' => Role::query()->whereNotIn('slug', ['student', 'parent', 'super-admin'])->value('id'),
    'department_id' => Department::query()->value('id'),
    'designation_id' => Designation::query()->value('id'),
    'joining_date' => now()->toDateString(),
    'employment_type' => 'full_time',
    'status' => 'active',
    'basic_salary' => 9000,
];

$invalid = req('POST', '/staff', $basePayload + ['category' => '']);
check('category is required', ! Staff::where('last_name', 'Probe')->exists(), 'status '.$invalid['status']);

$created = req('POST', '/staff', $basePayload + ['category' => 'non_academic']);
check('staff created with category', in_array($created['status'], [302, 303], true), 'status '.$created['status']);

$probe = Staff::where('phone', '+8801788880001')->latest('id')->first();
check('category stored', $probe && $probe->category === 'non_academic', $probe?->category);
check('label accessor works', $probe && $probe->category_label === 'Non-academic', $probe?->category_label);

if ($probe) {
    echo "\n=== Display + filter ===\n";
    $show = req('GET', '/staff/'.$probe->id);
    check('profile shows category', str_contains($show['body'], 'Non-academic'));

    $list = req('GET', '/staff?category=non_academic');
    check('filter by non-academic works', str_contains($list['body'], 'Category Probe'));

    $academicList = req('GET', '/staff?category=academic');
    check('academic filter excludes non-academic', ! str_contains($academicList['body'], 'Category Probe'));

    echo "\n=== Cleanup ===\n";
    DB::table('salary_structures')->where('staff_id', $probe->id)->delete();
    DB::table('staff_qualifications')->where('staff_id', $probe->id)->delete();
    $userId = $probe->user_id;
    DB::table('staff')->where('id', $probe->id)->delete();
    if ($userId && ! DB::table('staff')->where('user_id', $userId)->exists()) {
        DB::table('users')->where('id', $userId)->delete();
    }
    DB::table('audit_logs')->where('description', 'like', '%Category Probe%')->delete();
    echo "  removed probe staff #{$probe->id}\n";
}

echo "\n=== RESULT: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
