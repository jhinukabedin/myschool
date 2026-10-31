<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(): View
    {
        $permissions = Permission::orderBy('module')->orderBy('name')->get()->groupBy('module');

        return view('admin.roles', [
            'roles' => Role::withCount('users')->orderBy('name')->get(),
            'permissions' => $permissions,
            'rolePermissions' => Role::with('permissions')->get()->mapWithKeys(fn ($role) => [
                $role->id => $role->permissions->pluck('id')->all(),
            ]),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($role->slug === 'super-admin') {
            return back()->with('warning', 'The Super Administrator role always has unrestricted access and cannot be limited.');
        }

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $before = $role->permissions()->pluck('permissions.id')->all();
        $role->permissions()->sync($data['permissions'] ?? []);
        Cache::forget("role_permissions_{$role->id}");

        $this->audit->record(
            'role_permissions_updated',
            'Administration',
            "Permissions updated for role {$role->name}",
            $role,
            ['permissions' => $before],
            ['permissions' => $data['permissions'] ?? []]
        );

        return back()->with('success', "Permissions updated for {$role->name}.");
    }
}
