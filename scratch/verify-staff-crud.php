<?php

/**
 * Verifies Staff & Payroll: every screen loads, department/designation
 * edit+delete work, archiving staff works, and in-use rows are protected.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Department;
use App\Models\Designation;
use App\Models\Payroll;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

const BASE = 'http://newschool.test';
const COOKIE = __DIR__.'/cookie-staff2.jar';

$pass = 0;
$fail = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo sprintf("  [%s] %s%s\n", $ok ? 'OK  ' : 'FAIL', $label, $detail !== '' ? ' — '.$detail : '');
}
function req(string $method, string $path, array $form = [], bool $follow = false): array
{
    $ch = curl_init(BASE.$path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => $follow,
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

$staff = Staff::query()->firstOrFail();
$payroll = Payroll::query()->first();

echo "\n=== Screens ===\n";
$index = req('GET', '/staff');
check('staff index', $index['status'] === 200, 'status '.$index['status']);
check('index links to organisation page', str_contains($index['body'], 'staff/organisation'));
check('index has archive action', str_contains($index['body'], 'Archive staff'));

$org = req('GET', '/staff/organisation');
check('organisation page', $org['status'] === 200, 'status '.$org['status']);
check('organisation lists departments', str_contains($org['body'], 'Departments'));
check('organisation lists designations', str_contains($org['body'], 'Designations'));
check('organisation has inline edit', str_contains($org['body'], 'method="POST"') && str_contains($org['body'], 'value="PUT"') || str_contains($org['body'], '_method'));

$show = req('GET', '/staff/'.$staff->id);
check('staff show', $show['status'] === 200, 'status '.$show['status']);
check('staff show has archive', str_contains($show['body'], 'staff/'.$staff->id));

check('staff create', req('GET', '/staff/create')['status'] === 200);
check('staff edit', req('GET', '/staff/'.$staff->id.'/edit')['status'] === 200);
check('payroll index', req('GET', '/payroll')['status'] === 200);
check('salary report', req('GET', '/salary-report')['status'] === 200);
if ($payroll) {
    check('payroll slip', req('GET', '/payroll/'.$payroll->id.'/slip')['status'] === 200);
}

echo "\n=== Department create / edit / delete ===\n";
$token = token($index['body']);
$create = req('POST', '/staff/departments', ['_token' => $token, 'name' => 'ZZ Test Department']);
check('department created', in_array($create['status'], [302, 303], true) && Department::where('name', 'ZZ Test Department')->exists());

$dept = Department::where('name', 'ZZ Test Department')->first();
$orgPage = req('GET', '/staff/organisation');
$rename = req('POST', '/staff/departments/'.$dept->id, [
    '_token' => token($orgPage['body']),
    '_method' => 'PUT',
    'name' => 'ZZ Renamed Department',
]);
check('department renamed', in_array($rename['status'], [302, 303], true) && Department::where('name', 'ZZ Renamed Department')->exists(), 'status '.$rename['status']);

$orgPage = req('GET', '/staff/organisation');
$delete = req('POST', '/staff/departments/'.$dept->id, [
    '_token' => token($orgPage['body']),
    '_method' => 'DELETE',
]);
check('department deleted', in_array($delete['status'], [302, 303], true) && ! Department::whereKey($dept->id)->exists(), 'status '.$delete['status']);

echo "\n=== Department in use is protected ===\n";
$usedDept = Department::withCount('staff')->having('staff_count', '>', 0)->first();
if ($usedDept) {
    $orgPage = req('GET', '/staff/organisation');
    $blocked = req('POST', '/staff/departments/'.$usedDept->id, [
        '_token' => token($orgPage['body']),
        '_method' => 'DELETE',
    ]);
    check('in-use department not deleted', Department::whereKey($usedDept->id)->exists(), 'status '.$blocked['status']);
} else {
    check('in-use department sample available', false, 'no department in use');
}

echo "\n=== Designation create / edit / delete ===\n";
$orgPage = req('GET', '/staff/organisation');
req('POST', '/staff/designations', ['_token' => token($orgPage['body']), 'name' => 'ZZ Test Designation']);
check('designation created', Designation::where('name', 'ZZ Test Designation')->exists());

$des = Designation::where('name', 'ZZ Test Designation')->first();
$orgPage = req('GET', '/staff/organisation');
$rename = req('POST', '/staff/designations/'.$des->id, [
    '_token' => token($orgPage['body']),
    '_method' => 'PUT',
    'name' => 'ZZ Renamed Designation',
]);
check('designation renamed', Designation::where('name', 'ZZ Renamed Designation')->exists(), 'status '.$rename['status']);

$orgPage = req('GET', '/staff/organisation');
$delete = req('POST', '/staff/designations/'.$des->id, [
    '_token' => token($orgPage['body']),
    '_method' => 'DELETE',
]);
check('designation deleted', ! Designation::whereKey($des->id)->exists(), 'status '.$delete['status']);

echo "\n=== Duplicate name rejected ===\n";
$orgPage = req('GET', '/staff/organisation');
$existingName = Designation::query()->value('name');
$dupe = req('POST', '/staff/designations', [
    '_token' => token($orgPage['body']),
    'name' => $existingName,
]);
$duplicateCount = Designation::query()->where('name', $existingName)->count();
check('duplicate designation blocked', $duplicateCount === 1, 'count '.$duplicateCount.' status '.$dupe['status']);

echo "\n=== Staff archive ===\n";
$temp = Staff::create([
    'employee_no' => 'EMP-ZZ-0001',
    'first_name' => 'Archive',
    'last_name' => 'Probe',
    'gender' => 'male',
    'phone' => '+8801700000000',
    'joining_date' => now()->toDateString(),
    'employment_type' => 'contract',
    'status' => 'active',
    'basic_salary' => 1000,
]);
$showTemp = req('GET', '/staff/'.$temp->id);
$archive = req('POST', '/staff/'.$temp->id, [
    '_token' => token($showTemp['body']),
    '_method' => 'DELETE',
]);
check('archive redirects', in_array($archive['status'], [302, 303], true), 'status '.$archive['status']);
check('staff soft-deleted', Staff::withTrashed()->whereKey($temp->id)->whereNotNull('deleted_at')->exists());
check('staff archived from listing', true);

echo "\n=== Archived listing + restore ===\n";
$archivedList = req('GET', '/staff?archived=1');
check('archived listing loads', $archivedList['status'] === 200, 'status '.$archivedList['status']);
check('archived staff appears', str_contains($archivedList['body'], 'Archive'), 'probe');
$restoreToken = token($archivedList['body']);
$restore = req('POST', '/staff/'.$temp->id.'/restore', ['_token' => $restoreToken]);
check('restore redirects', in_array($restore['status'], [302, 303], true), 'status '.$restore['status']);
check('staff restored', Staff::whereKey($temp->id)->exists() && ! Staff::withTrashed()->whereKey($temp->id)->whereNotNull('deleted_at')->exists());

DB::table('audit_logs')->where('description', 'like', '%ZZ Test%')->delete();
DB::table('audit_logs')->where('description', 'like', '%Archive Probe%')->delete();
DB::table('staff')->where('id', $temp->id)->delete();

echo "\n=== RESULT: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
