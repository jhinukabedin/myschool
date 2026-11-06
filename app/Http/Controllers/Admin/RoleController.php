<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\PermissionMatrix;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoleController extends Controller
{
    /** Preferred role order on the access screen. */
    private const ROLE_ORDER = [
        'super-admin' => 0,
        'admin' => 1,
        'principal' => 2,
        'data-entry' => 3,
        'registrar' => 4,
        'teacher' => 5,
        'accountant' => 6,
        'staff' => 7,
        'media-manager' => 8,
        'website-admin' => 9,
        'website-editor' => 10,
        'student' => 20,
        'parent' => 21,
    ];

    public function __construct(
        private readonly AuditLogService $audit,
        private readonly PermissionMatrix $matrix
    ) {}

    public function index(Request $request): View
    {
        $editor = $request->user();

        $roles = Role::query()
            ->withCount('users')
            ->with('permissions')
            ->get()
            ->sortBy(fn (Role $role) => sprintf('%02d-%s', self::ROLE_ORDER[$role->slug] ?? 50, $role->name))
            ->values();

        $selected = $roles->firstWhere('slug', (string) $request->query('role'))
            ?? $roles->firstWhere('slug', 'admin')
            ?? $roles->first();

        return view('admin.roles', [
            'roles' => $roles,
            'selected' => $selected,
            'groups' => $this->matrix->groups(),
            'actions' => PermissionMatrix::ACTIONS,
            'selectedPermissions' => $selected?->permissions->pluck('id')->map(fn ($id) => (int) $id)->all() ?? [],
            'canEdit' => $this->canEditRole($editor, $selected),
            'editableRoles' => $roles->mapWithKeys(fn (Role $role) => [$role->id => $this->canEditRole($editor, $role)]),
            'deletableRoles' => $roles->mapWithKeys(fn (Role $role) => [$role->id => $this->canDeleteRole($editor, $role)]),
            'isSuper' => $editor->isSuperAdmin(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $base = Str::slug($data['name']) ?: 'role';
        $slug = $base;
        $suffix = 2;

        while (Role::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        $role = Role::create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'is_system' => false,
        ]);

        $this->audit->record(
            'role_created',
            'Administration',
            "Role {$role->name} created",
            $role,
            null,
            ['name' => $role->name, 'slug' => $role->slug]
        );

        return redirect()->route('roles.index', ['role' => $role->slug])
            ->with('success', "Role {$role->name} created. Now choose its permissions.");
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if (! $this->canEditRole($request->user(), $role)) {
            return back()->with('warning', 'You cannot change the permissions of this role.');
        }

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $editor = $request->user();
        $ceiling = $editor->isSuperAdmin()
            ? Permission::query()->pluck('slug')->all()
            : $editor->permissionSlugs();

        $submitted = Permission::query()->whereIn('id', $data['permissions'] ?? [])->pluck('slug');
        $notHeld = $submitted->diff($ceiling)->values()->all();

        if ($notHeld !== []) {
            throw ValidationException::withMessages([
                'permissions' => 'You can only give permissions you hold yourself: '.implode(', ', $notHeld).'.',
            ]);
        }

        // Permissions outside the editor's ceiling are left untouched.
        $outsideReach = $role->permissions()->pluck('slug')->diff($ceiling);
        $finalSlugs = $outsideReach->merge($submitted->intersect($ceiling))->unique()->values();
        $finalIds = Permission::query()->whereIn('slug', $finalSlugs)->pluck('id')->all();

        $before = $role->permissions()->pluck('permissions.id')->all();
        $role->permissions()->sync($finalIds);
        Cache::forget("role_permissions_{$role->id}");

        $this->audit->record(
            'role_permissions_updated',
            'Administration',
            "Permissions updated for role {$role->name}",
            $role,
            ['permissions' => $before],
            ['permissions' => $finalIds]
        );

        return redirect()->route('roles.index', ['role' => $role->slug])
            ->with('success', "Permissions updated for {$role->name}.");
    }

    public function updateDetails(Request $request, Role $role): RedirectResponse
    {
        if (! $this->canEditRole($request->user(), $role)) {
            return back()->with('warning', 'You cannot change the details of this role.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $before = ['name' => $role->name, 'description' => $role->description];
        $role->update($data);

        $this->audit->record(
            'role_updated',
            'Administration',
            "Role {$role->name} updated",
            $role,
            $before,
            $data
        );

        return redirect()->route('roles.index', ['role' => $role->slug])
            ->with('success', "Role {$role->name} updated.");
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        if (! $this->canDeleteRole($request->user(), $role)) {
            return back()->with('warning', 'This role cannot be deleted.');
        }

        if ($role->users()->exists()) {
            return back()->with('warning', 'Move the users to another role before deleting this role.');
        }

        $this->audit->record(
            'role_deleted',
            'Administration',
            "Role {$role->name} deleted",
            $role,
            ['name' => $role->name, 'slug' => $role->slug],
            null
        );

        $role->permissions()->detach();
        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Role deleted.');
    }

    private function canDeleteRole(?User $editor, ?Role $role): bool
    {
        return $editor !== null
            && $role !== null
            && $this->canEditRole($editor, $role)
            && ! $role->is_system;
    }

    private function canEditRole(?User $editor, ?Role $role): bool
    {
        if (! $editor || ! $role) {
            return false;
        }

        // The super administrator role always bypasses permission checks.
        if ($role->slug === 'super-admin') {
            return false;
        }

        if ($editor->isSuperAdmin()) {
            return true;
        }

        // School administrators can delegate to other roles, not to their own role.
        return $role->slug !== 'admin';
    }
}
