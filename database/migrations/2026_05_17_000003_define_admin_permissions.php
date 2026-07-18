<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Define the granular permissions that gate the admin-only routes,
 * and seed sensible defaults per role. Routes migrate from
 * `role:headmaster|admin` to permission-based middleware in the same
 * change; this migration is what makes the new gates pass on day one.
 *
 *   manage users     — /users CRUD (sysadmin)
 *   manage backups   — /backups (sysadmin)
 *   manage rollover  — /rollover (sysadmin, but academic-adjacent)
 *   manage settings  — /settings (both: admin uses academic tabs,
 *                                  system_admin uses modules/permissions)
 *
 * admin (school administration): user management + academic settings.
 *   Hiring/firing staff and class/subject setup is the front-office job.
 * system_admin (technical): backups + rollover + settings. The IT role.
 * headmaster: `manage settings` only. Headmaster is pedagogical —
 *   onboarding new users isn't realistically their day-to-day.
 *
 * Any school can re-grant via the Settings → Permissions matrix UI.
 */
return new class extends Migration {
    public function up(): void
    {
        $permissions = [
            'manage users',
            'manage backups',
            'manage rollover',
            'manage settings',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $headmaster = Role::where('name', 'headmaster')->first();
        $admin = Role::where('name', 'admin')->first();
        $systemAdmin = Role::where('name', 'system_admin')->first();

        $headmaster?->givePermissionTo('manage settings');
        $admin?->givePermissionTo(['manage users', 'manage settings']);
        $systemAdmin?->givePermissionTo(['manage backups', 'manage rollover', 'manage settings']);

        if (app()->bound(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        // No rollback — silently dropping permissions could lock people out.
    }
};
