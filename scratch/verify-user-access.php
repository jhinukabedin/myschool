<?php

/**
 * Verifies the permission delegation model: School Administrator hands out only
 * the permissions they hold, staff stay closed until enabled, and admin accounts
 * cannot be edited from User Access.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
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

function login(string $path, string $email, string $password, string $jarPath): void
{
    global $jar;
    $jar = $jarPath;
    @unlink($jarPath);
    $page = req('GET', $path);
    req('POST', $path, ['_token' => token($page['body']), 'email' => $email, 'password' => $password]);
}

function hasExplicit(User $user, string $slug): bool
{
    return $user->explicitPermissions()->where('slug', $slug)->exists();
}

// --- Setup: a staff member with no permissions yet --------------------------
$registrar = Role::where('slug', 'registrar')->firstOrFail();
$staff = User::updateOrCreate(
    ['email' => 'qa-staff@edusphere.test'],
    ['role_id' => $registrar->id, 'name' => 'QA Staff', 'password' => 'Staff@12345', 'is_active' => true]
);
DB::table('user_permissions')->where('user_id', $staff->id)->delete();

// --- Backfill and role rules -------------------------------------------------
$teacher = User::where('email', 'teacher@edusphere.test')->first();
check('existing teacher keeps permissions after upgrade', $teacher && hasExplicit($teacher, 'attendance.view'));
$adminUser = User::where('email', 'school.admin@edusphere.test')->first();
check('school admin uses role permissions (not explicit rows)', $adminUser && ! $adminUser->explicitPermissions()->exists() && $adminUser->hasPermission('access.manage'));
check('super admin holds every permission', User::where('email', 'admin@edusphere.test')->first()->hasPermission('subscription.manage'));
check('new staff starts with no permissions', ! $staff->fresh()->hasPermission('dashboard.view'));

// --- Staff stays closed until enabled ---------------------------------------
login('/login', 'qa-staff@edusphere.test', 'Staff@12345', __DIR__.'/cookie-ua-staff.jar');
$dash = req('GET', '/dashboard');
check('staff without permission cannot open the dashboard', $dash['status'] === 403, (string) $dash['status']);

// --- School admin hands out permissions -------------------------------------
login('/login', 'school.admin@edusphere.test', 'SchoolAdmin@12345', __DIR__.'/cookie-ua-admin.jar');

$page = req('GET', route('access.index', [], false).'?user='.$staff->id);
check('user access page opens for school admin', $page['status'] === 200, (string) $page['status']);
check('page lists the staff member', str_contains($page['body'], 'QA Staff'));
check('super-admin-only permissions are shown locked', str_contains($page['body'], 'subscription.manage') && str_contains($page['body'], 'perm-row is-disabled'));

$save = req('PUT', route('access.update', ['user' => $staff->id], false), [
    '_token' => token($page['body']),
    'permissions' => ['dashboard.view', 'students.create', 'students.view'],
]);
check('admin saves permissions for staff', $save['status'] === 302, (string) $save['status']);
check('explicit rows written', hasExplicit($staff, 'students.create') && hasExplicit($staff, 'dashboard.view'));

$forbidden = req('PUT', route('access.update', ['user' => $staff->id], false), [
    '_token' => token($page['body']),
    'permissions' => ['dashboard.view', 'subscription.manage'],
]);
check('admin cannot give a permission they do not hold', $forbidden['status'] === 302 && ! hasExplicit($staff, 'subscription.manage'), (string) $forbidden['status']);

$superEdit = req('PUT', route('access.update', ['user' => 1], false), ['_token' => token($page['body']), 'permissions' => []]);
check('admin cannot change the super administrator', $superEdit['status'] === 403, (string) $superEdit['status']);

$selfEdit = req('PUT', route('access.update', ['user' => $adminUser->id], false), ['_token' => token($page['body']), 'permissions' => []]);
check('admin cannot change another admin account here', $selfEdit['status'] === 403, (string) $selfEdit['status']);

// --- Staff now has what was given ------------------------------------------
login('/login', 'qa-staff@edusphere.test', 'Staff@12345', __DIR__.'/cookie-ua-staff.jar');
$dash = req('GET', '/dashboard');
check('staff opens the dashboard after being enabled', $dash['status'] === 200, (string) $dash['status']);
check('permitted menu is visible in the sidebar', str_contains($dash['body'], 'All Students'));
check('menu without permission is hidden from the sidebar', ! str_contains($dash['body'], 'Fee Structure'));

// --- Admin cannot take what staff holds beyond admin's ceiling (kept) -------
login('/login', 'school.admin@edusphere.test', 'SchoolAdmin@12345', __DIR__.'/cookie-ua-admin.jar');
$page = req('GET', route('access.index', [], false).'?user='.$staff->id);
$revoke = req('PUT', route('access.update', ['user' => $staff->id], false), ['_token' => token($page['body']), 'permissions' => ['students.create']]);
check('admin can remove a permission', $revoke['status'] === 302 && ! hasExplicit($staff, 'dashboard.view'));

// --- Role template respects the admin's ceiling -----------------------------
$template = req('POST', route('access.template', ['user' => $staff->id], false), ['_token' => token($page['body'])]);
check('role template applies', $template['status'] === 302, (string) $template['status']);
$staffRefreshed = $staff->fresh();
check('template never gives permissions the admin lacks', ! hasExplicit($staffRefreshed, 'subscription.manage') && ! hasExplicit($staffRefreshed, 'users.manage'));

// --- Cleanup ---------------------------------------------------------------
$staff->explicitPermissions()->detach();
$staff->forceDelete();
@unlink(__DIR__.'/cookie-ua-staff.jar');
@unlink(__DIR__.'/cookie-ua-admin.jar');

echo PHP_EOL."=== RESULT: $passed passed, $failed failed ===\n";
exit($failed > 0 ? 1 : 0);
