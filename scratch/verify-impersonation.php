<?php

/**
 * Verifies super administrator impersonation: login as another user, see that
 * user's real permissions, and return safely to the super administrator.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;

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
    ]);
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form, '', '&'));
    }
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => (string) $body];
}

function token(string $html): ?string
{
    if (preg_match('/name="csrf-token"\s+content="([^"]+)"/', $html, $m)) {
        return $m[1];
    }

    return preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m) ? $m[1] : null;
}

function login(string $email, string $password, string $jarPath): void
{
    global $jar;
    $jar = $jarPath;
    @unlink($jarPath);
    $page = req('GET', '/login');
    req('POST', '/login', ['_token' => token($page['body']), 'email' => $email, 'password' => $password]);
}

$accountant = User::where('email', 'accountant@edusphere.test')->firstOrFail();
$student = User::where('email', 'student@edusphere.test')->firstOrFail();
$superId = User::where('email', 'admin@edusphere.test')->value('id');

// --- Super administrator can impersonate a staff member --------------------
login('admin@edusphere.test', 'Admin@12345', __DIR__.'/cookie-imp-super.jar');

$access = req('GET', '/admin/access?user='.$accountant->id);
check('access page loads', $access['status'] === 200, (string) $access['status']);
check('access page shows the login-as button', str_contains($access['body'], 'Login as Farhan'));
check('login-as posts to the impersonation route', str_contains($access['body'], '/impersonate/'.$accountant->id));

$start = req('POST', '/impersonate/'.$accountant->id, ['_token' => token($access['body'])]);
check('impersonation starts', $start['status'] === 302, (string) $start['status']);

$dashboard = req('GET', '/dashboard');
check('impersonated dashboard loads', $dashboard['status'] === 200, (string) $dashboard['status']);
check('dashboard shows the impersonated user', str_contains($dashboard['body'], $accountant->name));
check('impersonation banner is visible', str_contains($dashboard['body'], 'impersonation-bar') && str_contains($dashboard['body'], 'Viewing as'));
check('banner offers a return button', str_contains($dashboard['body'], 'Return to System Administrator'));

$blocked = req('GET', '/admin/access');
check('impersonated user keeps their real permissions', $blocked['status'] === 403, (string) $blocked['status']);

$stop = req('POST', '/impersonate/stop', ['_token' => token($dashboard['body'])]);
check('impersonation stops', $stop['status'] === 302, (string) $stop['status']);

$back = req('GET', '/dashboard');
check('back on the super administrator dashboard', $back['status'] === 200 && str_contains($back['body'], 'System Administrator'));
check('banner is gone after returning', ! str_contains($back['body'], 'impersonation-bar'));
check('super administrator can open roles again', req('GET', '/roles')['status'] === 200);

// Signing out inside the impersonated view must not destroy the admin session.
$page = req('GET', '/dashboard');
req('POST', '/impersonate/'.$accountant->id, ['_token' => token($page['body'])]);
$inside = req('GET', '/dashboard');
$signOut = req('POST', '/logout', ['_token' => token($inside['body'])]);
check('sign out while impersonating returns to the super administrator', $signOut['status'] === 302);
check('super administrator session survives the impersonated sign out', str_contains(req('GET', '/dashboard')['body'], 'System Administrator'));

// --- Portal accounts can also be previewed and exited ----------------------
$page = req('GET', '/dashboard');
$portalStart = req('POST', '/impersonate/'.$student->id, ['_token' => token($page['body'])]);
check('student impersonation starts', $portalStart['status'] === 302, (string) $portalStart['status']);

$portal = req('GET', '/student/dashboard');
check('student portal opens for the super administrator', $portal['status'] === 200, (string) $portal['status']);
check('portal shows the impersonation banner', str_contains($portal['body'], 'impersonation-bar'));

$portalStop = req('POST', '/impersonate/stop', ['_token' => token($portal['body'])]);
check('portal impersonation stops', $portalStop['status'] === 302, (string) $portalStop['status']);
check('super administrator restored from the portal', str_contains(req('GET', '/dashboard')['body'], 'System Administrator'));

// --- Rules: only super admin, never self, never inactive -------------------
login('school.admin@edusphere.test', 'SchoolAdmin@12345', __DIR__.'/cookie-imp-admin.jar');
$adminDash = req('GET', '/dashboard');
$notAllowed = req('POST', '/impersonate/'.$accountant->id, ['_token' => token($adminDash['body'])]);
check('school administrator cannot impersonate', $notAllowed['status'] === 403, (string) $notAllowed['status']);
check('school administrator stays signed in', str_contains(req('GET', '/dashboard')['body'], 'School Administrator'));

login('admin@edusphere.test', 'Admin@12345', __DIR__.'/cookie-imp-super.jar');
$superDash = req('GET', '/dashboard');
$self = req('POST', '/impersonate/'.$superId, ['_token' => token($superDash['body'])]);
check('super administrator cannot impersonate themselves', $self['status'] === 403, (string) $self['status']);

$staffRole = Role::where('slug', 'staff')->firstOrFail();
$inactive = User::create([
    'role_id' => $staffRole->id,
    'name' => 'Inactive QA User',
    'email' => 'inactive-qa@edusphere.test',
    'password' => 'Temporary@12345',
    'is_active' => false,
]);
$inactiveTry = req('POST', '/impersonate/'.$inactive->id, ['_token' => token($superDash['body'])]);
check('inactive accounts cannot be impersonated', $inactiveTry['status'] === 403, (string) $inactiveTry['status']);

$inactive->forceDelete();

// --- Audit trail -----------------------------------------------------------
$started = AuditLog::where('action', 'impersonation_started')->where('created_at', '>=', now()->subMinutes(5))->count();
$stopped = AuditLog::where('action', 'impersonation_stopped')->where('created_at', '>=', now()->subMinutes(5))->count();
check('impersonation start is audited', $started >= 2, (string) $started);
check('impersonation stop is audited', $stopped >= 2, (string) $stopped);
$startLog = AuditLog::where('action', 'impersonation_started')->latest('id')->first();
$stopLog = AuditLog::where('action', 'impersonation_stopped')->latest('id')->first();
check('start is attributed to the super administrator', $startLog?->user_id === $superId, (string) $startLog?->user_id);
check('stop is attributed to the super administrator', $stopLog?->user_id === $superId, (string) $stopLog?->user_id);

@unlink(__DIR__.'/cookie-imp-super.jar');
@unlink(__DIR__.'/cookie-imp-admin.jar');

echo PHP_EOL."=== RESULT: $passed passed, $failed failed ===\n";
exit($failed > 0 ? 1 : 0);
