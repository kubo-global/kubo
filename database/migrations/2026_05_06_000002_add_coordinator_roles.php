<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    public function up(): void
    {
        app()['cache']->forget('spatie.permission.cache');

        Role::firstOrCreate(['name' => 'assistant_coordinator', 'guard_name' => 'web']);
    }

    public function down(): void
    {
        Role::where('name', 'assistant_coordinator')->delete();
    }
};
