<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'permission_id']);
        });

        // Existing staff keep what their role already allowed, so nobody loses
        // access on upgrade. Self-managed roles and portal roles are excluded.
        $excludedRoles = DB::table('roles')
            ->whereIn('slug', ['super-admin', 'admin', 'student', 'parent'])
            ->pluck('id');

        $users = DB::table('users')
            ->whereNotNull('role_id')
            ->whereNotIn('role_id', $excludedRoles)
            ->get(['id', 'role_id']);

        $now = now();
        foreach ($users as $user) {
            $permissionIds = DB::table('permission_role')
                ->where('role_id', $user->role_id)
                ->pluck('permission_id');

            $rows = $permissionIds->map(fn ($permissionId) => [
                'user_id' => $user->id,
                'permission_id' => $permissionId,
                'granted_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            if ($rows !== []) {
                DB::table('user_permissions')->insertOrIgnore($rows);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
    }
};
