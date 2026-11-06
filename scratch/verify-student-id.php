<?php

/**
 * Verifies the permanent Student ID is visible across the application.
 * Logs in over HTTP and checks rendered HTML for the known Student ID of an
 * existing student, plus the new ID format on key screens.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

const BASE = 'http://newschool.test';
const COOKIE = __DIR__.'/cookie-id.jar';

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
    $headers = ['Accept: text/html'];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => COOKIE,
        CURLOPT_COOKIEFILE => COOKIE,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 60,
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

echo "\n=== Login ===\n";
$login = req('GET', '/login');
$csrf = token($login['body']);
check('login page', $login['status'] === 200 && $csrf !== null, 'status '.$login['status']);
req('POST', '/login', ['_token' => $csrf, 'email' => 'admin@edusphere.test', 'password' => 'Admin@12345']);
$dash = req('GET', '/dashboard');
check('dashboard loads', $dash['status'] === 200, 'status '.$dash['status']);

// Pick a student in the current session with a fee row so fee screens work.
$student = Student::query()->whereNotNull('student_code')->orderBy('id')->firstOrFail();
$code = $student->student_code;

echo "\n=== Data sanity ===\n";
check('all students have an ID', Student::whereNull('student_code')->orWhere('student_code', '')->count() === 0);
check('ID is digits only', preg_match('/^\d{7}$/', $code) === 1, $code);
check('IDs are unique', Student::select('student_code')->groupBy('student_code')->havingRaw('COUNT(*) > 1')->count() === 0);

echo "\n=== Student list ===\n";
$list = req('GET', '/students');
check('student list loads', $list['status'] === 200, 'status '.$list['status']);
check('list shows "Student ID" header', str_contains($list['body'], 'Student ID'));

// Look for any real ID rendered on the first page.
$visibleCode = null;
foreach (Student::whereNotNull('student_code')->pluck('student_code') as $candidate) {
    if (str_contains($list['body'], (string) $candidate)) {
        $visibleCode = (string) $candidate;
        break;
    }
}
check('list shows an actual Student ID', $visibleCode !== null, (string) $visibleCode);

echo "\n=== Student profile ===\n";
$profile = req('GET', '/students/'.$student->id);
check('profile loads', $profile['status'] === 200, 'status '.$profile['status']);
check('profile shows Student ID label', str_contains($profile['body'], 'Student ID'));
check('profile shows the ID value', str_contains($profile['body'], $code), $code);

echo "\n=== Search by Student ID ===\n";
$search = req('GET', '/students?q='.$code);
check('search by ID returns the student', str_contains($search['body'], $student->full_name), $student->full_name);

echo "\n=== Admission form ===\n";
$adm = req('GET', '/admissions/create');
check('admission form loads', $adm['status'] === 200, 'status '.$adm['status']);
check('admission form documents the ID format', str_contains($adm['body'], '2603001'));

echo "\n=== Attendance ===\n";
$enrollmentForAttendance = Enrollment::query()
    ->where('status', 'active')
    ->orderBy('id')
    ->first();

if ($enrollmentForAttendance) {
    $attendance = req('GET', '/attendance?class_id='.$enrollmentForAttendance->class_id.'&section_id='.$enrollmentForAttendance->section_id);
    check('attendance page loads', $attendance['status'] === 200, 'status '.$attendance['status']);
    check('attendance shows Student ID', str_contains($attendance['body'], 'Student ID'));
    $attendanceCode = Student::whereKey($enrollmentForAttendance->student_id)->value('student_code');
    check('attendance shows the student ID value', str_contains($attendance['body'], (string) $attendanceCode), (string) $attendanceCode);
} else {
    check('attendance test enrolment available', false, 'no active enrolment');
}

echo "\n=== Fee collection ===\n";
$fee = req('GET', '/fees/collection');
check('fee collection loads', $fee['status'] === 200, 'status '.$fee['status']);
check('fee collection shows Student ID', str_contains($fee['body'], $code), $code);

$enrollment = Enrollment::where('student_id', $student->id)->first();
if ($enrollment) {
    $show = req('GET', '/fees/collection/'.$student->id);
    check('fee ledger loads', $show['status'] === 200, 'status '.$show['status']);
    check('fee ledger shows Student ID', str_contains($show['body'], $code), $code);
}

echo "\n=== Scholarships ===\n";
$sch = req('GET', '/fees/scholarships');
check('scholarship dashboard loads', $sch['status'] === 200, 'status '.$sch['status']);
check('scholarship page shows Student ID', str_contains($sch['body'], 'Student ID'));

echo "\n=== Reports ===\n";
$report = req('GET', '/students/archive');
check('archive loads', $report['status'] === 200, 'status '.$report['status']);
check('archive shows Student ID header', str_contains($report['body'], 'Student ID'));

echo "\n=== RESULT: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
