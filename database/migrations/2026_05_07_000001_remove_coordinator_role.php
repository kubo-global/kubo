<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    public function up(): void
    {
        app()['cache']->forget('spatie.permission.cache');
        Role::where('name', 'coordinator')->delete();
    }

    public function down(): void
    {
        Role::firstOrCreate(['name' => 'coordinator', 'guard_name' => 'web']);
    }
};
