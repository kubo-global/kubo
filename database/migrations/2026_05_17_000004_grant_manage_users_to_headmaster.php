<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Headmasters do manage users in practice — onboarding teachers,
 * resetting passwords for staff, etc. The original define-permissions
 * migration left them with `manage settings` only because the split
 * was still being thought through. Restore `manage users` as a
 * headmaster default; admin keeps it too (both can manage staff).
 */
return new class extends Migration {
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'manage users', 'guard_name' => 'web']);

        Role::where('name', 'headmaster')->first()?->givePermissionTo('manage users');

        if (app()->bound(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        // No rollback — we don't want to silently strip headmaster's
        // ability to manage users.
    }
};
