<?php
namespace Database\Seeders;

use App\Models\School;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Roles + permissions first (RolesAndPermissionsSeeder is not idempotent —
        // it must run exactly once), then the full demo dataset.
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(DemoSeeder::class);

        // DemoSeeder sets school_id on everything it creates; backfill any
        // legacy rows from other seeders as a safe no-op.
        $schoolId = School::first()->id;
        foreach (['users', 'subjects', 'grades', 'schoolyears'] as $table) {
            if (Schema::hasColumn($table, 'school_id')) {
                DB::table($table)->whereNull('school_id')->update(['school_id' => $schoolId]);
            }
        }
    }
}
