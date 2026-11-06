<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

const BASE = 'http://newschool.test';
const COOKIE = __DIR__.'/cookie-adm.jar';

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

$session = current_session();
$class = SchoolClass::where('name', 'Class 1')->firstOrFail(); // level 3 → code 04
$section = $class->sections()->firstOrFail();

echo "\n=== Submit an admission ===\n";
$form = token(req('GET', '/admissions/create')['body']);
$payload = [
    '_token' => $form,
    'first_name' => 'IdProof',
    'last_name' => 'Student',
    'has_sibling' => '0',
    'gender' => 'male',
    'date_of_birth' => '2017-04-04',
    'academic_session_id' => $session->id,
    'class_id' => $class->id,
    'section_id' => $section->id,
    'admission_date' => now()->toDateString(),
    'guardian_name' => 'Proof Guardian',
    'guardian_relation' => 'Father',
    'guardian_phone' => '+880 1755 000111',
];

$res = req('POST', '/admissions', $payload);
check('admission submitted', in_array($res['status'], [302, 303], true), 'status '.$res['status']);

$student = Student::where('first_name', 'IdProof')->where('last_name', 'Student')->latest('id')->first();

if (! $student) {
    check('student created', false);
} else {
    check('student created', true, '#'.$student->id);
    check('Student ID generated on admission', filled($student->student_code), (string) $student->student_code);
    check('ID is 7 digits', preg_match('/^\d{7}$/', (string) $student->student_code) === 1, (string) $student->student_code);
    check('ID year = 26', str_starts_with((string) $student->student_code, '26'), (string) $student->student_code);
    check('ID class code = 04 (Class 1, level 3)', substr((string) $student->student_code, 2, 2) === '04', (string) $student->student_code);

    $profile = req('GET', '/students/'.$student->id);
    check('success/profile page shows the new ID', str_contains($profile['body'], (string) $student->student_code), (string) $student->student_code);

    // Cleanup
    $userIds = DB::table('student_portal_links')->where('student_id', $student->id)->pluck('user_id')->all();
    DB::table('student_portal_links')->where('student_id', $student->id)->delete();
    DB::table('parent_student_links')->where('student_id', $student->id)->delete();
    $orphans = DB::table('users')->whereIn('id', $userIds)
        ->whereNotIn('id', DB::table('student_portal_links')->pluck('user_id'))
        ->whereNotIn('id', DB::table('parent_student_links')->pluck('user_id'))
        ->pluck('id')->all();
    if ($orphans) {
        DB::table('users')->whereIn('id', $orphans)->delete();
    }
    DB::table('enrollments')->where('student_id', $student->id)->delete();
    DB::table('student_guardians')->where('student_id', $student->id)->delete();
    DB::table('students')->where('id', $student->id)->delete();
    DB::table('audit_logs')->where('description', 'like', '%IdProof%')->delete();
    echo "  cleaned up test student #{$student->id}\n";
}

echo "\n=== RESULT: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
