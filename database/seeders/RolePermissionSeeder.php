<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('school.permissions') as $slug => $meta) {
            Permission::updateOrCreate(
                ['slug' => $slug],
                ['name' => $meta['name'], 'module' => $meta['module']]
            );
        }

        foreach (config('school.roles') as $slug => $meta) {
            $isSystem = $meta['is_system'] ?? true;
            $existing = Role::where('slug', $slug)->first();

            // Starter roles such as Principal and Data Entry are editable and
            // belong to the school. Never overwrite a school's own changes.
            if ($existing && ! $isSystem) {
                continue;
            }

            $role = Role::updateOrCreate(
                ['slug' => $slug],
                ['name' => $meta['name'], 'description' => $meta['description'], 'is_system' => $isSystem]
            );

            $permissionIds = $meta['permissions'] === '*'
                ? Permission::pluck('id')->all()
                : Permission::whereIn('slug', $meta['permissions'])->pluck('id')->all();

            $role->permissions()->sync($permissionIds);
        }
    }
}
