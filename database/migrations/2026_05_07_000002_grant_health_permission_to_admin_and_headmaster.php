<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    public function up(): void
    {
        app()['cache']->forget('spatie.permission.cache');

        $permission = Permission::firstOrCreate(['name' => 'view medical records', 'guard_name' => 'web']);

        foreach (['headmaster', 'admin'] as $name) {
            $role = Role::where('name', $name)->first();
            $role?->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        app()['cache']->forget('spatie.permission.cache');

        foreach (['headmaster', 'admin'] as $name) {
            $role = Role::where('name', $name)->first();
            $role?->revokePermissionTo('view medical records');
        }
    }
};
