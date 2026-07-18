<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Older production installs predate the School model. They ran on the
 * legacy `school_parameters` key/value table and never had a row in
 * `schools` — so `assessment_types` was empty too, and the grade-entry
 * UI showed no assessment types to pick from.
 *
 * This migration is the production-side equivalent of SchoolSeeder +
 * the school_id backfill block at the bottom of DatabaseSeeder.
 * Idempotent: returns immediately if a school already exists.
 */
return new class extends Migration {
    public function up(): void
    {
        if (DB::table('schools')->exists()) {
            return;
        }

        // Fresh installs and test databases have no users yet — don't
        // seed a placeholder School there. Only legacy production installs
        // that predate the schools table have users without a school row.
        if (!DB::table('users')->exists()) {
            return;
        }

        $testWeight = (float) (DB::table('school_parameters')->where('key', 'testWeight')->value('value') ?? 0.25);
        $examWeight = (float) (DB::table('school_parameters')->where('key', 'examWeight')->value('value') ?? 0.75);
        $now = now();

        $schoolId = DB::table('schools')->insertGetId([
            'name' => 'Default School',
            'motto' => 'Excellence in Education',
            'timezone' => 'Africa/Lagos',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('assessment_types')->insert([
            ['school_id' => $schoolId, 'name' => 'Test', 'weight' => $testWeight, 'display_order' => 1, 'default_max_score' => null, 'created_at' => $now, 'updated_at' => $now],
            ['school_id' => $schoolId, 'name' => 'Exam', 'weight' => $examWeight, 'display_order' => 2, 'default_max_score' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Gambian lower-basic grade key (grade 1 = best); editable per school in Settings.
        DB::table('grading_scales')->insert([
            ['school_id' => $schoolId, 'label' => '1', 'min_score' => 80, 'max_score' => 100, 'remark' => 'Excellent', 'display_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['school_id' => $schoolId, 'label' => '4', 'min_score' => 70, 'max_score' => 79.99, 'remark' => 'Very Good', 'display_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['school_id' => $schoolId, 'label' => '5', 'min_score' => 60, 'max_score' => 69.99, 'remark' => 'Good', 'display_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['school_id' => $schoolId, 'label' => '6', 'min_score' => 50, 'max_score' => 59.99, 'remark' => 'Average', 'display_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['school_id' => $schoolId, 'label' => '8', 'min_score' => 40, 'max_score' => 49.99, 'remark' => 'Pass', 'display_order' => 5, 'created_at' => $now, 'updated_at' => $now],
            ['school_id' => $schoolId, 'label' => '9', 'min_score' => 0, 'max_score' => 39.99, 'remark' => 'Fail', 'display_order' => 6, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Backfill school_id on legacy rows that predate the schools table.
        // Mirrors the block at the bottom of DatabaseSeeder.
        foreach (['users', 'subjects', 'grades', 'schoolyears'] as $table) {
            if (Schema::hasColumn($table, 'school_id')) {
                DB::table($table)->whereNull('school_id')->update(['school_id' => $schoolId]);
            }
        }
    }

    public function down(): void
    {
        // Not reversible — destructive rollback would delete a working school.
    }
};
