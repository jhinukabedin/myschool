<?php

/**
 * Verifies feature-level unlocks: a locked module can open one feature for a role,
 * students and parents stay out of the admin panel, and revoking works.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Role;
use App\Models\SchoolSetting;
use Illuminate\Support\Facades\DB;

const BASE = 'http://newschool.test';

$passed = 0;
$failed = 0;
$jar = '';

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  [OK  ] $label\n";
    } else {
        $failed++;
        echo "  [FAIL] $label".($detail !== '' ? " ($detail)" : '')."\n";
    }
}

function req(string $method, string $path, array $form = []): array
{
    global $jar;
    $ch = curl_init(BASE.$path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_HTTPHEADER => ['Accept: text/html'],
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HEADER => true,
    ]);
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
    }
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $headers = substr((string) $raw, 0, $headerSize);
    preg_match('/^Location:\s*(\S+)/mi', $headers, $m);

    return ['status' => $status, 'body' => (string) substr((string) $raw, $headerSize), 'location' => $m[1] ?? null];
}

function token(string $html): ?string
{
    return preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m) ? $m[1] : null;
}

function login(string $path, string $email, string $password, string $jarPath): array
{
    global $jar;
    $jar = $jarPath;
    @unlink($jarPath);
    $page = req('GET', $path);

    return req('POST', $path, [
        '_token' => token($page['body']),
        'email' => $email,
        'password' => $password,
    ]);
}

function saveModules(array $modules): void
{
    global $jar;
    $page = req('GET', '/admin/subscription');
    req('POST', '/admin/subscription', [
        '_token' => token($page['body']),
        '_method' => 'PUT',
        'modules' => $modules,
        'subscription_note' => '',
    ]);
}

function saveGrants(array $exceptions): array
{
    $page = req('GET', '/admin/subscription');

    return req('POST', '/admin/subscription/feature-access', [
        '_token' => token($page['body']),
        '_method' => 'PUT',
        'exceptions' => $exceptions,
    ]);
}

$schoolAdmin = Role::where('slug', 'admin')->firstOrFail();
$adminId = $schoolAdmin->id;

// Start from a clean state: every paid module locked, no unlocks.
login('/login', 'admin@edusphere.test', 'Admin@12345', __DIR__.'/cookie-fa-super.jar');
saveModules([]);
DB::table('role_feature_grants')->delete();

// --- Super admin unlocks two locked features for the school admin ------------

$res = saveGrants(['class-routine', 'notices']);
check('super admin saves feature unlocks', $res['status'] === 302, (string) $res['status']);
check('unlock rows stored', DB::table('role_feature_grants')->where('role_id', $adminId)->count() === 2);

$denied = req('PUT', '/admin/subscription/feature-access', []);
check('super admin route is CSRF/permission protected', in_array($denied['status'], [302, 419, 403], true), (string) $denied['status']);

// --- School admin sees exactly the unlocked features -------------------------
login('/login', 'school.admin@edusphere.test', 'SchoolAdmin@12345', __DIR__.'/cookie-fa-admin.jar');

$routine = req('GET', route('routines.index', [], false));
check('unlocked class routine opens for school admin', $routine['status'] === 200, (string) $routine['status']);

$notices = req('GET', route('notices.index', [], false));
check('unlocked notice board opens for school admin', $notices['status'] === 200, (string) $notices['status']);

$students = req('GET', route('students.index', [], false));
check('locked student records stay closed', $students['status'] === 403, (string) $students['status']);

$structures = req('GET', route('fees.structures.index', [], false));
check('locked fee structures stay closed', $structures['status'] === 403, (string) $structures['status']);

$exams = req('GET', route('exams.index', [], false));
check('locked exams stay closed', $exams['status'] === 403, (string) $exams['status']);

$dash = req('GET', '/dashboard');
check('sidebar links the unlocked routine', str_contains($dash['body'], route('routines.index', [], false).'"'));
check('unlocked feature is not marked locked in sidebar', ! str_contains($dash['body'], 'title="Class routine is locked'));
check('locked feature is marked locked in sidebar', str_contains($dash['body'], 'All Students is locked') || str_contains($dash['body'], 'All Students is locked'));

$website = req('GET', '/admin/website');
check('website content still open', $website['status'] === 200, (string) $website['status']);

$subscription = req('GET', '/admin/subscription');
check('school admin sees the unlock summary', str_contains($subscription['body'], 'Opened for your role'));
check('school admin cannot save feature unlocks', $subscription['status'] === 200 && ! str_contains($subscription['body'], 'Save feature access'));

$hack = saveGrants(['students']);
check('school admin cannot change feature unlocks', $hack['status'] === 403, (string) $hack['status']);

// --- Plan enable keeps the unlock rows (not deleted) -------------------------
login('/login', 'admin@edusphere.test', 'Admin@12345', __DIR__.'/cookie-fa-super.jar');
saveModules(['academics']);
check('enabling academics keeps notice unlock', DB::table('role_feature_grants')->where('role_id', $adminId)->where('feature_key', 'notices')->exists());
login('/login', 'school.admin@edusphere.test', 'SchoolAdmin@12345', __DIR__.'/cookie-fa-admin.jar');
$routine = req('GET', route('routines.index', [], false));
check('academics plan opens routine without unlock', $routine['status'] === 200, (string) $routine['status']);
$students = req('GET', route('students.index', [], false));
check('students still locked while only academics enabled', $students['status'] === 403, (string) $students['status']);

// --- Revoke -----------------------------------------------------------------
login('/login', 'admin@edusphere.test', 'Admin@12345', __DIR__.'/cookie-fa-super.jar');
saveModules([]);
saveGrants([]);
check('revoked unlocks are removed', DB::table('role_feature_grants')->where('role_id', $adminId)->count() === 0);
login('/login', 'school.admin@edusphere.test', 'SchoolAdmin@12345', __DIR__.'/cookie-fa-admin.jar');
$routine = req('GET', route('routines.index', [], false));
check('routine locked again after revoke', $routine['status'] === 403, (string) $routine['status']);

// --- Portal separation -------------------------------------------------------
$studentStaffLogin = login('/login', 'student@edusphere.test', 'Admin@12345', __DIR__.'/cookie-fa-student.jar');
check('student blocked from staff login', $studentStaffLogin['status'] === 200 || ($studentStaffLogin['status'] === 302 && str_contains((string) $studentStaffLogin['location'], '/login')), (string) $studentStaffLogin['status']);
$studentDashAsStaff = req('GET', '/dashboard');
check('student is not logged into the admin panel', ! str_contains($studentDashAsStaff['body'], 'Website Content'));

$studentPortal = login('/student/login', 'student@edusphere.test', 'Admin@12345', __DIR__.'/cookie-fa-student.jar');
check('student signs in through the portal', $studentPortal['status'] === 302, (string) $studentPortal['status']);
$dashRedirect = req('GET', '/dashboard');
check('student opening /dashboard goes to the student portal', $dashRedirect['status'] === 302 && str_contains((string) $dashRedirect['location'], '/student/dashboard'), (string) $dashRedirect['location']);
$adminRoute = req('GET', route('students.index', [], false));
check('student opening an admin page goes to the student portal', $adminRoute['status'] === 302 && str_contains((string) $adminRoute['location'], '/student/dashboard'), (string) $adminRoute['status']);

// --- Cleanup ----------------------------------------------------------------
DB::table('role_feature_grants')->delete();
foreach (array_keys(config('school.modules')) as $key) {
    if (! config("school.modules.$key.core")) {
        SchoolSetting::put("module_{$key}", '0', 'subscription');
    }
}
@unlink(__DIR__.'/cookie-fa-super.jar');
@unlink(__DIR__.'/cookie-fa-admin.jar');
@unlink(__DIR__.'/cookie-fa-student.jar');

echo PHP_EOL."=== RESULT: $passed passed, $failed failed ===\n";
exit($failed > 0 ? 1 : 0);
