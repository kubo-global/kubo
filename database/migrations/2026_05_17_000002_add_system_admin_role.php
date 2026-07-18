<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Split the overloaded `admin` role:
 *
 *   - `admin` stays as the school-administration role (manage students,
 *     classes, enrollments, etc.). It loses default health access — that
 *     becomes configurable via the Settings → Permissions matrix.
 *
 *   - `system_admin` (new) takes the technical/sysadmin work — user
 *     management, backups, rollover, deeper system config.
 *
 * To preserve existing access, every user that currently has `admin`
 * also gets `system_admin` here. New users created from /users will
 * see both checkboxes and can be granted only one if the school wants
 * the split to start mattering.
 */
return new class extends Migration {
    public function up(): void
    {
        $modelType = 'App\\Models\\User';

        // 1. Create the system_admin role if it doesn't exist yet.
        $existing = DB::table('roles')->where('name', 'system_admin')->first();
        $systemAdminId = $existing
            ? $existing->id
            : DB::table('roles')->insertGetId([
                'name' => 'system_admin',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        // 2. Promote every current admin to also have system_admin.
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        if ($adminRoleId) {
            $adminUserIds = DB::table('model_has_roles')
                ->where('role_id', $adminRoleId)
                ->where('model_type', $modelType)
                ->pluck('model_id');

            foreach ($adminUserIds as $userId) {
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $systemAdminId,
                    'model_type' => $modelType,
                    'model_id' => $userId,
                ]);
            }
        }

        // 3. Clear spatie's permission cache so the new role is visible
        //    on the next request without a manual cache:clear.
        if (app()->bound(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        // No rollback — we don't want to silently demote users who may
        // have been onboarded as system_admin only.
    }
};
