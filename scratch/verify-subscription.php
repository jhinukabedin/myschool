<?php

/**
 * Verifies subscription module locks and role-based access.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\User;

const BASE = 'http://newschool.test';

$passed = 0;
$failed = 0;
$jar = null;

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

function req(string $method, string $path, array $form = [], bool $follow = false): array
{
    global $jar;
    $ch = curl_init(BASE.$path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_HTTPHEADER => ['Accept: text/html'],
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

function login(string $email, string $password, string $jarPath): bool
{
    global $jar;
    $jar = $jarPath;
    $login = req('GET', '/login');
    $res = req('POST', '/login', [
        '_token' => token($login['body']),
        'email' => $email,
        'password' => $password,
    ]);

    return $res['status'] === 302;
}

function saveModules(array $modules, ?string $note = null): array
{
    $page = req('GET', '/admin/subscription');
    $token = token($page['body']);

    return req('POST', '/admin/subscription', [
        '_token' => $token,
        '_method' => 'PUT',
        'modules' => $modules,
        'subscription_note' => $note ?? '',
    ]);
}

@unlink(__DIR__.'/cookie-super.jar');
@unlink(__DIR__.'/cookie-accountant.jar');
@unlink(__DIR__.'/cookie-registrar.jar');
@unlink(__DIR__.'/cookie-admin.jar');

// --- Super administrator -------------------------------------------------
check('super admin login', login('admin@edusphere.test', 'Admin@12345', __DIR__.'/cookie-super.jar'));

$page = req('GET', '/admin/subscription');
check('subscription page loads', $page['status'] === 200, (string) $page['status']);
check('subscription page lists modules', str_contains($page['body'], 'Module access') && str_contains($page['body'], 'Website Content'));
check('subscription page lists every menu', substr_count($page['body'], 'class="menu-name"') === 70, (string) substr_count($page['body'], 'class="menu-name"'));
check('locked modules still list their menus', substr_count($page['body'], 'This module is locked. Switch it on') >= 1);
check('system access menus are always available', str_contains($page['body'], 'System Access') && str_contains($page['body'], 'Roles &amp; Access'));
check('website is always on', str_contains($page['body'], 'Always on'));
check('super admin sees toggles', str_contains($page['body'], 'name="modules[]"'));

$students = req('GET', '/students');
check('super admin bypasses module locks', $students['status'] === 200, (string) $students['status']);

$dash = req('GET', '/dashboard');
check('super admin sidebar has no locked items', ! str_contains($dash['body'], 'aria-disabled="true"'));
check('super admin sidebar shows subscription', str_contains($dash['body'], '/admin/subscription'));

// Enable two modules.
$res = saveModules(['students', 'fees'], 'QA verification');
check('enable students + fees saves', $res['status'] === 302, (string) $res['status']);
check('students setting is now 1', school_setting('module_students') === '1');
check('fees setting is now 1', school_setting('module_fees') === '1');

// --- Accountant ----------------------------------------------------------
check('accountant login', login('accountant@edusphere.test', 'Admin@12345', __DIR__.'/cookie-accountant.jar'));

$students = req('GET', '/students');
check('accountant can open enabled students module', $students['status'] === 200, (string) $students['status']);

$fees = req('GET', '/fees/structures');
check('accountant can open enabled fees module', $fees['status'] === 200, (string) $fees['status']);

$website = req('GET', '/admin/website');
check('accountant is blocked by role on website', $website['status'] === 403, (string) $website['status']);

$dash = req('GET', '/dashboard');
check('accountant sidebar shows locked modules', str_contains($dash['body'], 'aria-disabled="true"'));
check('accountant sidebar hides subscription', ! str_contains($dash['body'], '/admin/subscription'));

// --- Lock everything again ----------------------------------------------
$jar = __DIR__.'/cookie-super.jar';
$res = saveModules([], null);
check('lock all modules saves', $res['status'] === 302, (string) $res['status']);
check('students setting is now 0', school_setting('module_students') === '0');
check('website remains enabled', school_setting('module_website') === '1');

// --- Registrar (website.view, no super admin) ----------------------------
check('registrar login', login('registrar@edusphere.test', 'Admin@12345', __DIR__.'/cookie-registrar.jar'));

$website = req('GET', '/admin/website');
check('registrar can open core website module', $website['status'] === 200, (string) $website['status']);

$dash = req('GET', '/dashboard');
check('registrar sidebar shows locked modules', str_contains($dash['body'], 'aria-disabled="true"'));
check('dashboard hides locked fee metrics', ! str_contains($dash['body'], 'Fees Collected'));
check('dashboard shows the locked notice', str_contains($dash['body'], 'modules locked.'));
check('dashboard keeps the website quick action', str_contains($dash['body'], 'Website Content'));

$students = req('GET', '/students');
check('registrar cannot open locked students module', $students['status'] === 403, (string) $students['status']);
check('locked page explains the lock', str_contains($students['body'], 'This page is not enabled for your school yet'));

// --- School administrator: read-only subscription page -------------------
$adminRole = Role::where('slug', 'admin')->first();
$temp = null;
if ($adminRole) {
    $temp = User::updateOrCreate(
        ['email' => 'qa-admin@edusphere.test'],
        [
            'role_id' => $adminRole->id,
            'name' => 'QA School Admin',
            'password' => bcrypt('Admin@12345'),
            'is_active' => true,
        ]
    );

    check('school admin login', login('qa-admin@edusphere.test', 'Admin@12345', __DIR__.'/cookie-admin.jar'));
    $page = req('GET', '/admin/subscription');
    check('school admin can view subscription status', $page['status'] === 200, (string) $page['status']);
    check('school admin sees read-only message', str_contains($page['body'], 'read-only'));
    check('school admin has no save button', ! str_contains($page['body'], 'Save module access'));
    check('school admin still sees every menu', substr_count($page['body'], 'class="menu-name"') === 70, (string) substr_count($page['body'], 'class="menu-name"'));
    check('school admin sees the locked-module note', str_contains($page['body'], 'Contact the super administrator to enable it'));

    $dash = req('GET', '/dashboard');
    check('school admin sidebar shows subscription', str_contains($dash['body'], '/admin/subscription'));

    $update = req('POST', '/admin/subscription', [
        '_token' => token($page['body']),
        '_method' => 'PUT',
        'modules' => ['students'],
    ]);
    check('school admin cannot change module access', $update['status'] === 403, (string) $update['status']);

    $students = req('GET', '/students');
    check('school admin is blocked by module lock', $students['status'] === 403, (string) $students['status']);

    $website = req('GET', '/admin/website');
    check('school admin can open website content', $website['status'] === 200, (string) $website['status']);

    $temp->forceDelete();
    check('temporary admin cleaned up', User::where('email', 'qa-admin@edusphere.test')->doesntExist());
}

// Final state: all paid modules locked, website core enabled.
SchoolSetting::put('subscription_note', null, 'subscription');
SchoolSetting::put('subscription_updated_at', now()->toDateTimeString(), 'subscription');

@unlink(__DIR__.'/cookie-super.jar');
@unlink(__DIR__.'/cookie-accountant.jar');
@unlink(__DIR__.'/cookie-registrar.jar');
@unlink(__DIR__.'/cookie-admin.jar');

echo PHP_EOL."=== RESULT: $passed passed, $failed failed ===\n";
exit($failed > 0 ? 1 : 0);
