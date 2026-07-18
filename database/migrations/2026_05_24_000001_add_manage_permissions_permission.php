<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Split the role × permission matrix (the access-control surface itself)
 * out from the general `manage settings` permission into its own
 * `manage permissions`.
 *
 * Rationale: whoever can edit the matrix can grant themselves anything,
 * so it's effectively the superuser key. We don't want all three of
 * headmaster/admin/system_admin holding it just because they can open
 * Settings.
 *
 * Defaults — both get it, and it's configurable from the matrix itself:
 *   headmaster   — so a small school with no dedicated IT person can
 *                  still configure everything out of the box.
 *   system_admin — the technical owner.
 *   admin        — NOT granted. School administration manages staff and
 *                  academic structure but not the access matrix.
 *
 * A school with stricter governance can uncheck `manage permissions`
 * from headmaster in the matrix, leaving system_admin as sole owner.
 */
return new class extends Migration {
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'manage permissions', 'guard_name' => 'web']);

        Role::where('name', 'headmaster')->first()?->givePermissionTo('manage permissions');
        Role::where('name', 'system_admin')->first()?->givePermissionTo('manage permissions');

        if (app()->bound(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        // Not reversible — don't silently strip the matrix key.
    }
};
