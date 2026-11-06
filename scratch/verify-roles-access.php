<?php

/**
 * Verifies the User Roles & Access screen and permission matrix.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Permission;
use App\Models\Role;

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

$teacher = Role::where('slug', 'teacher')->firstOrFail();
$originalTeacher = $teacher->permissions()->pluck('permissions.id')->all();
$viewStudents = Permission::where('slug', 'students.view')->value('id');
$createStudents = Permission::where('slug', 'students.create')->value('id');

// --- Super administrator ---------------------------------------------------
login('admin@edusphere.test', 'Admin@12345', __DIR__.'/cookie-ra-super.jar');

$page = req('GET', '/roles');
check('roles page loads', $page['status'] === 200, (string) $page['status']);
check('screen shows the new title', str_contains($page['body'], 'User Roles &amp; Access'));
check('screen lists every action column', str_contains($page['body'], '>View<') && str_contains($page['body'], '>Add<') && str_contains($page['body'], '>Edit<') && str_contains($page['body'], '>Delete<') && str_contains($page['body'], '>Print<') && str_contains($page['body'], '>Export<'));
check('screen shows module groups', str_contains($page['body'], 'Module / Feature') && str_contains($page['body'], 'Fees &amp; Payments') && str_contains($page['body'], 'System Setting'));
check('screen lists roles', str_contains($page['body'], 'Administrator') && str_contains($page['body'], 'Teacher') && str_contains($page['body'], 'Accountant'));
check('sidebar links to the roles screen', str_contains($page['body'], '>Roles &amp; Access<'));
check('add-role dialog is present', str_contains($page['body'], 'id="new-role-dialog"') && str_contains($page['body'], 'Add New Role'));
check('edit-role dialog is present', str_contains($page['body'], 'id="edit-role-dialog"') && str_contains($page['body'], 'Edit Role'));
check('every permission row shows its page badge', substr_count($page['body'], 'class="ra-page-badge"') === Permission::count(), (string) substr_count($page['body'], 'class="ra-page-badge"'));
check('role list has an action menu per role', substr_count($page['body'], 'class="ra-role-menu"') === Role::count(), (string) substr_count($page['body'], 'class="ra-role-menu"'));

$teacherPage = req('GET', '/roles?role=teacher');
check('a role can be selected', $teacherPage['status'] === 200 && str_contains($teacherPage['body'], 'Role Permissions <span>– Teacher</span>'));
check('selected role permissions are checked', substr_count($teacherPage['body'], 'checked') > 5);

// Save teacher permissions through the matrix form.
$save = req('POST', '/roles/'.$teacher->id, [
    '_token' => token($teacherPage['body']),
    '_method' => 'PUT',
    'permissions' => [$viewStudents, $createStudents],
]);
check('role permissions save', $save['status'] === 302, (string) $save['status']);
$teacher->refresh();
check('saved permissions match the matrix', $teacher->permissions()->pluck('permissions.id')->sort()->values()->all() === collect([$viewStudents, $createStudents])->sort()->values()->all());

// Super administrator role stays unrestricted.
$super = Role::where('slug', 'super-admin')->firstOrFail();
$superPage = req('GET', '/roles?role=super-admin');
$superSave = req('POST', '/roles/'.$super->id, [
    '_token' => token($superPage['body']),
    '_method' => 'PUT',
    'permissions' => [],
]);
check('super administrator role cannot be limited', $superSave['status'] === 302 && $super->permissions()->count() === Permission::count());

// A new role can be created and starts empty.
$newRole = req('POST', '/roles', [
    '_token' => token($page['body']),
    'name' => 'QA Access Role',
    'description' => 'Temporary role for verification',
]);
check('new role can be created', $newRole['status'] === 302, (string) $newRole['status']);
$created = Role::where('slug', 'qa-access-role')->first();
check('new role stored', $created !== null);
check('new role starts with no permissions', $created && $created->permissions()->count() === 0);

// Role details can be renamed through the edit dialog endpoint.
$rename = req('POST', '/roles/'.$created->id.'/details', [
    '_token' => token($page['body']),
    '_method' => 'PUT',
    'name' => 'QA Renamed Role',
    'description' => 'Renamed by verification',
]);
check('role can be renamed', $rename['status'] === 302, (string) $rename['status']);
$created->refresh();
check('renamed role keeps its slug and new name', $created->name === 'QA Renamed Role' && $created->slug === 'qa-access-role');

// System roles cannot be deleted.
$deleteSuper = req('POST', '/roles/'.$super->id, [
    '_token' => token($page['body']),
    '_method' => 'DELETE',
]);
check('system role cannot be deleted', $deleteSuper['status'] === 302 && Role::whereKey($super->id)->exists());

// Roles that still have users cannot be deleted.
$adminRole = Role::where('slug', 'admin')->firstOrFail();
$deleteAdmin = req('POST', '/roles/'.$adminRole->id, [
    '_token' => token($page['body']),
    '_method' => 'DELETE',
]);
check('role with users cannot be deleted', $deleteAdmin['status'] === 302 && Role::whereKey($adminRole->id)->exists());

// A custom role with no users is deleted.
$deleteCustom = req('POST', '/roles/'.$created->id, [
    '_token' => token($page['body']),
    '_method' => 'DELETE',
]);
check('custom role can be deleted', $deleteCustom['status'] === 302 && ! Role::whereKey($created->id)->exists());
$created = null;

// --- School administrator cannot open role management ----------------------
login('school.admin@edusphere.test', 'SchoolAdmin@12345', __DIR__.'/cookie-ra-admin.jar');
$adminPage = req('GET', '/roles');
check('school administrator cannot manage roles directly', $adminPage['status'] === 403, (string) $adminPage['status']);

$adminDash = req('GET', '/dashboard');
$adminSave = req('POST', '/roles/'.$teacher->id, [
    '_token' => token($adminDash['body']),
    '_method' => 'PUT',
    'permissions' => [$viewStudents],
]);
check('school administrator cannot change role permissions', $adminSave['status'] === 403, (string) $adminSave['status']);

// Cleanup.
$teacher->permissions()->sync($originalTeacher);
$created?->permissions()->detach();
$created?->forceDelete();
@unlink(__DIR__.'/cookie-ra-super.jar');
@unlink(__DIR__.'/cookie-ra-admin.jar');

$teacher->refresh();
check('teacher permissions restored', $teacher->permissions()->count() === count($originalTeacher));

echo PHP_EOL."=== RESULT: $passed passed, $failed failed ===\n";
exit($failed > 0 ? 1 : 0);
