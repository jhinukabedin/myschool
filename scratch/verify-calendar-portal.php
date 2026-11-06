<?php

/**
 * Verifies the academic calendar (holidays + weekly off) and its effect on
 * attendance, plus the staff self-service portal.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Holiday;
use App\Models\SchoolSetting;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffLeave;
use App\Models\User;
use Illuminate\Support\Facades\DB;

const BASE = 'http://newschool.test';
const COOKIE = __DIR__.'/cookie-calendar.jar';

$pass = 0;
$fail = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo sprintf("  [%s] %s%s\n", $ok ? 'OK  ' : 'FAIL', $label, $detail !== '' ? ' — '.$detail : '');
}
function req(string $method, string $path, array $form = [], string $cookie = COOKIE): array
{
    $ch = curl_init(BASE.$path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie,
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

echo "\n=== Calendar page ===\n";
$calendar = req('GET', '/holidays');
check('calendar loads', $calendar['status'] === 200, 'status '.$calendar['status']);
check('shows weekly off section', str_contains($calendar['body'], 'Weekly off days'));
check('has add holiday', str_contains($calendar['body'], 'Add holiday'));

echo "\n=== Create a holiday ===\n";
$holidayDate = now()->addDays(30)->toDateString();
$create = req('POST', '/holidays', [
    '_token' => token($calendar['body']),
    'name' => 'ZZ Test Holiday',
    'type' => 'public_holiday',
    'start_date' => $holidayDate,
    'end_date' => $holidayDate,
    'applies_to' => 'all',
]);
check('holiday created', in_array($create['status'], [302, 303], true) && Holiday::where('name', 'ZZ Test Holiday')->exists(), 'status '.$create['status']);

$holiday = Holiday::where('name', 'ZZ Test Holiday')->first();

echo "\n=== Holiday detection ===\n";
check('student detection', Holiday::forDate($holidayDate, 'students') !== null);
check('staff detection', Holiday::forDate($holidayDate, 'staff') !== null);
check('non-holiday date is clear', Holiday::forDate(now()->addDays(31)->toDateString(), 'students') === null);

echo "\n=== Attendance blocked on the holiday ===\n";
$staffPage = req('GET', '/staff/attendance?date='.$holidayDate);
check('staff attendance shows holiday banner', str_contains($staffPage['body'], 'ZZ Test Holiday'));

$staff = Staff::query()->firstOrFail();
$blocked = req('POST', '/staff/attendance', [
    '_token' => token($staffPage['body']),
    'date' => $holidayDate,
    'statuses' => [$staff->id => 'present'],
]);
check('holiday save rejected', StaffAttendance::whereDate('attendance_date', $holidayDate)->count() === 0, 'rows '.StaffAttendance::whereDate('attendance_date', $holidayDate)->count());

$class = \App\Models\SchoolClass::query()->first();
$section = $class?->sections()->first();
if ($class && $section) {
    $studentPage = req('GET', '/attendance?date='.$holidayDate.'&class_id='.$class->id.'&section_id='.$section->id);
    check('student attendance shows holiday banner', str_contains($studentPage['body'], 'ZZ Test Holiday'));
    check('save button disabled', str_contains($studentPage['body'], 'disabled'));
}

echo "\n=== Weekly off setting ===\n";
$saved = req('POST', '/holidays/weekly-off', [
    '_token' => token(req('GET', '/holidays')['body']),
    'weekdays' => ['Friday', 'Saturday'],
]);
check('weekly off saved', in_array($saved['status'], [302, 303], true), 'status '.$saved['status']);
check('setting persisted', Holiday::weeklyOffDays() === ['Friday', 'Saturday'], implode(',', Holiday::weeklyOffDays()));

$nextFriday = now()->next('Friday');
check('friday detected as weekly off', Holiday::forDate($nextFriday, 'students')['is_weekly_off'] ?? false);

// restore default and clean the probe holiday
SchoolSetting::put('holiday_weekdays', json_encode(['Friday']), 'academics');
DB::table('holidays')->where('id', $holiday->id)->delete();

echo "\n=== Staff portal access ===\n";
$staffUser = User::query()->whereHas('staff')->where('is_active', true)->first();
check('a staff login exists', $staffUser !== null, (string) $staffUser?->username);

if ($staffUser) {
    $staffCookie = __DIR__.'/cookie-staff-portal.jar';
    @unlink($staffCookie);

    // Use a known temporary password for the HTTP login, then restore the hash.
    $originalHash = $staffUser->password;
    $staffUser->forceFill(['password' => bcrypt('PortalTest@123')])->save();

    $loginPage = req('GET', '/login', [], $staffCookie);
    $attempt = req('POST', '/login', [
        '_token' => token($loginPage['body']),
        'email' => $staffUser->username ?: $staffUser->email,
        'password' => 'PortalTest@123',
    ], $staffCookie);

    $portal = req('GET', '/staff-portal/dashboard', [], $staffCookie);
    check('staff portal dashboard', $portal['status'] === 200, 'status '.$portal['status'].' (login '.$attempt['status'].')');

    if ($portal['status'] === 200) {
        check('dashboard shows employee no', str_contains($portal['body'], $staffUser->staff->employee_no));
        check('has payslips link', str_contains($portal['body'], '/staff-portal/payslips'));
        check('has payments link', str_contains($portal['body'], '/staff-portal/payments'));

        check('leaves page', req('GET', '/staff-portal/leaves', [], $staffCookie)['status'] === 200);
        check('payslips page', req('GET', '/staff-portal/payslips', [], $staffCookie)['status'] === 200);
        check('payments page', req('GET', '/staff-portal/payments', [], $staffCookie)['status'] === 200);
        check('profile page', req('GET', '/staff-portal/profile', [], $staffCookie)['status'] === 200);

        $leavePage = req('GET', '/staff-portal/leaves', [], $staffCookie);
        $apply = req('POST', '/staff-portal/leaves', [
            '_token' => token($leavePage['body']),
            'leave_type' => 'casual',
            'start_date' => now()->addDays(40)->toDateString(),
            'end_date' => now()->addDays(40)->toDateString(),
            'reason' => 'ZZ portal self-service probe',
        ], $staffCookie);
        check('self-service leave applied', in_array($apply['status'], [302, 303], true), 'status '.$apply['status']);

        $created = StaffLeave::where('staff_id', $staffUser->staff->id)->where('reason', 'ZZ portal self-service probe')->first();
        check('leave stored as pending', $created && $created->status === 'pending');

        DB::table('staff_leaves')->where('reason', 'ZZ portal self-service probe')->delete();
    }

    $staffUser->forceFill(['password' => $originalHash])->save();
    @unlink($staffCookie);
}

echo "\n=== RESULT: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
