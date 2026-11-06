<?php

/**
 * Smoke-tests every Staff & Payroll screen to find broken pages.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Payroll;
use App\Models\Staff;

const BASE = 'http://newschool.test';
const COOKIE = __DIR__.'/cookie-staff.jar';

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

$staff = Staff::query()->firstOrFail();
$payroll = Payroll::query()->first();

echo "\n=== Staff pages ===\n";
$index = req('GET', '/staff');
check('staff index', $index['status'] === 200, 'status '.$index['status']);
check('staff index has Create button', str_contains($index['body'], 'Add staff'));
check('staff index has Department modal', str_contains($index['body'], 'new-department'));
check('staff index has Designation modal', str_contains($index['body'], 'new-designation'));

$create = req('GET', '/staff/create');
check('staff create', $create['status'] === 200, 'status '.$create['status']);

$show = req('GET', '/staff/'.$staff->id);
check('staff show', $show['status'] === 200, 'status '.$show['status']);
check('staff show has salary structure form', str_contains($show['body'], 'salary-structure'));

$edit = req('GET', '/staff/'.$staff->id.'/edit');
check('staff edit', $edit['status'] === 200, 'status '.$edit['status']);

echo "\n=== Payroll pages ===\n";
$pay = req('GET', '/payroll');
check('payroll index', $pay['status'] === 200, 'status '.$pay['status']);
check('payroll index has generate form', str_contains($pay['body'], '/payroll/generate'));

if ($payroll) {
    $slip = req('GET', '/payroll/'.$payroll->id.'/slip');
    check('payroll slip', $slip['status'] === 200, 'status '.$slip['status']);
}

$report = req('GET', '/salary-report');
check('salary report', $report['status'] === 200, 'status '.$report['status']);

echo "\n=== Missing CRUD checks ===\n";
check('department edit route exists', \Illuminate\Support\Facades\Route::has('staff.departments.update'));
check('department delete route exists', \Illuminate\Support\Facades\Route::has('staff.departments.destroy'));
check('designation edit route exists', \Illuminate\Support\Facades\Route::has('staff.designations.update'));
check('designation delete route exists', \Illuminate\Support\Facades\Route::has('staff.designations.destroy'));
check('staff delete/archive route exists', \Illuminate\Support\Facades\Route::has('staff.destroy'));

echo "\n=== RESULT: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
