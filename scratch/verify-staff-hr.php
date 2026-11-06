<?php

/**
 * Verifies the small HR module: staff attendance marking and staff leave
 * application / approval / rejection / cancellation.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffLeave;
use Illuminate\Support\Facades\DB;

const BASE = 'http://newschool.test';
const COOKIE = __DIR__.'/cookie-hr.jar';

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

$staff = Staff::query()->where('status', 'active')->get();
check('active staff available', $staff->count() >= 2, (string) $staff->count());

echo "\n=== Staff attendance ===\n";
$attendance = req('GET', '/staff/attendance');
check('attendance page loads', $attendance['status'] === 200, 'status '.$attendance['status']);
check('has status selects', str_contains($attendance['body'], 'statuses['));
check('has save button', stripos($attendance['body'], 'Save attendance') !== false);
check('lists a staff member', str_contains($attendance['body'], $staff->first()->full_name));

$date = now()->toDateString();
// Staff attendance is blocked on weekly offs and holidays, so pick the next
// working day when the suite happens to run on one.
while (\App\Models\Holiday::forDate($date, 'staff')) {
    $date = date('Y-m-d', strtotime($date.' +1 day'));
}
$statuses = [];
foreach ($staff->take(3) as $index => $member) {
    $statuses[$member->id] = ['present', 'late', 'absent'][$index % 3];
}

$save = req('POST', '/staff/attendance', [
    '_token' => token($attendance['body']),
    'date' => $date,
    'statuses' => $statuses,
    'notes' => [$staff->first()->id => 'Arrived early'],
]);
check('attendance saved', in_array($save['status'], [302, 303], true), 'status '.$save['status']);
check('records stored', StaffAttendance::whereDate('attendance_date', $date)->count() >= count($statuses), (string) StaffAttendance::whereDate('attendance_date', $date)->count());
check('status persisted', StaffAttendance::where('staff_id', array_key_first($statuses))->whereDate('attendance_date', $date)->value('status') === reset($statuses));
check('note persisted', StaffAttendance::where('staff_id', $staff->first()->id)->whereDate('attendance_date', $date)->value('note') === 'Arrived early');

// Re-saving updates instead of duplicating.
$page = req('GET', '/staff/attendance?date='.$date);
$firstId = $staff->first()->id;
$resave = req('POST', '/staff/attendance', [
    '_token' => token($page['body']),
    'date' => $date,
    'statuses' => [$firstId => 'present'],
]);
check('re-save keeps one row per staff/day', StaffAttendance::where('staff_id', $firstId)->whereDate('attendance_date', $date)->count() === 1, (string) StaffAttendance::where('staff_id', $firstId)->whereDate('attendance_date', $date)->count());
check('status update applied', StaffAttendance::where('staff_id', $firstId)->whereDate('attendance_date', $date)->value('status') === 'present');

echo "\n=== Staff leave ===\n";
$leavesPage = req('GET', '/staff/leaves');
check('leave page loads', $leavesPage['status'] === 200, 'status '.$leavesPage['status']);
check('has leave form', str_contains($leavesPage['body'], 'staff/leaves'));

$start = now()->addDays(10)->toDateString();
$end = now()->addDays(12)->toDateString();

$store = req('POST', '/staff/leaves', [
    '_token' => token($leavesPage['body']),
    'staff_id' => $staff->first()->id,
    'leave_type' => 'sick',
    'start_date' => $start,
    'end_date' => $end,
    'reason' => 'Medical treatment',
]);
check('leave stored', in_array($store['status'], [302, 303], true), 'status '.$store['status']);

$leave = StaffLeave::where('staff_id', $staff->first()->id)->latest('id')->first();
check('leave pending', $leave && $leave->status === 'pending', $leave?->status);
check('days computed (3)', $leave && (int) $leave->days === 3, (string) $leave?->days);

echo "\n=== Overlap protection ===\n";
$page = req('GET', '/staff/leaves');
$overlap = req('POST', '/staff/leaves', [
    '_token' => token($page['body']),
    'staff_id' => $staff->first()->id,
    'leave_type' => 'casual',
    'start_date' => now()->addDays(11)->toDateString(),
    'end_date' => now()->addDays(13)->toDateString(),
    'reason' => 'Overlapping request',
]);
$overlapCount = StaffLeave::where('staff_id', $staff->first()->id)->count();
check('overlapping leave rejected', $overlapCount === 1, 'rows '.$overlapCount);

echo "\n=== Approve / reject / cancel ===\n";
$page = req('GET', '/staff/leaves');
$approve = req('POST', '/staff/leaves/'.$leave->id.'/approve', ['_token' => token($page['body'])]);
check('approve works', $leave->fresh()->status === 'approved', $leave->fresh()->status);
check('approver recorded', $leave->fresh()->approved_by !== null && $leave->fresh()->approved_at !== null);

$second = StaffLeave::create([
    'staff_id' => $staff[1]->id,
    'leave_type' => 'casual',
    'start_date' => now()->addDays(20)->toDateString(),
    'end_date' => now()->addDays(20)->toDateString(),
    'days' => 1,
    'reason' => 'Personal errand',
    'status' => 'pending',
]);
$page = req('GET', '/staff/leaves');
$reject = req('POST', '/staff/leaves/'.$second->id.'/reject', ['_token' => token($page['body']), 'decision_note' => 'Too many pending requests']);
check('reject works', $second->fresh()->status === 'rejected', $second->fresh()->status);

$page = req('GET', '/staff/leaves');
$cancel = req('POST', '/staff/leaves/'.$leave->id.'/cancel', ['_token' => token($page['body'])]);
check('cancel works', $leave->fresh()->status === 'cancelled', $leave->fresh()->status);

echo "\n=== Cleanup ===\n";
DB::table('staff_leaves')->whereIn('id', [$leave->id, $second->id])->delete();
DB::table('staff_attendances')->whereDate('attendance_date', $date)->delete();
DB::table('audit_logs')->whereIn('action', ['staff_attendance_marked', 'staff_leave_requested', 'staff_leave_approved', 'staff_leave_rejected', 'staff_leave_cancelled'])
    ->where('created_at', '>=', now()->subMinutes(10))->delete();
echo "  removed test attendance and leave rows\n";

echo "\n=== RESULT: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
