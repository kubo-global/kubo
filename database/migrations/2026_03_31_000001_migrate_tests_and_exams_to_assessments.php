<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migrate all test/exam data into the unified assessment tables.
 *
 * Strategy:
 * 1. Create assessment_types (Test + Exam) with weights from school_parameters
 * 2. Add temporary legacy columns to assessments for mapping
 * 3. Copy tests → assessments, exams → assessments
 * 4. Copy test_scores → assessment_scores, exam_scores → assessment_scores
 * 5. Copy topic associations
 */
return new class extends Migration
{
    public function up(): void
    {
        $school = DB::table('schools')->first();
        if (!$school) {
            return; // No school — nothing to migrate
        }

        // Read weights from school_parameters (production values)
        $testWeight = (float) (DB::table('school_parameters')->where('key', 'testWeight')->value('value') ?? 0.25);
        $examWeight = (float) (DB::table('school_parameters')->where('key', 'examWeight')->value('value') ?? 0.75);

        // Step 1: Create assessment types
        $testTypeId = DB::table('assessment_types')->insertGetId([
            'school_id' => $school->id,
            'name' => 'Test',
            'weight' => $testWeight,
            'display_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $examTypeId = DB::table('assessment_types')->insertGetId([
            'school_id' => $school->id,
            'name' => 'Exam',
            'weight' => $examWeight,
            'display_order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Step 2: Add temporary legacy columns for precise ID mapping
        Schema::table('assessments', function (Blueprint $table) {
            $table->unsignedInteger('legacy_id')->nullable()->after('id');
            $table->string('legacy_type', 10)->nullable()->after('legacy_id');
            $table->index(['legacy_id', 'legacy_type']);
        });

        // Step 3: Copy tests → assessments
        DB::statement("
            INSERT INTO assessments (legacy_id, legacy_type, assessment_type_id, subject_id, offering_id, term_id, date, name, info, max_score, confirmed, created_at, updated_at)
            SELECT id, 'test', ?, subject_id, offering_id, term_id, date, name, info, max_score, confirmed, created_at, updated_at
            FROM tests
        ", [$testTypeId]);

        // Copy exams → assessments
        DB::statement("
            INSERT INTO assessments (legacy_id, legacy_type, assessment_type_id, subject_id, offering_id, term_id, date, name, info, max_score, confirmed, created_at, updated_at)
            SELECT id, 'exam', ?, subject_id, offering_id, term_id, date, name, info, max_score, confirmed, created_at, updated_at
            FROM exams
        ", [$examTypeId]);

        // Step 4: Copy test_scores → assessment_scores
        DB::statement("
            INSERT INTO assessment_scores (user_id, assessment_id, score, excused, created_at, updated_at)
            SELECT ts.user_id, a.id, ts.score, ts.excused, ts.created_at, ts.updated_at
            FROM test_scores ts
            JOIN assessments a ON a.legacy_id = ts.test_id AND a.legacy_type = 'test'
        ");

        // Copy exam_scores → assessment_scores
        DB::statement("
            INSERT INTO assessment_scores (user_id, assessment_id, score, excused, created_at, updated_at)
            SELECT es.user_id, a.id, es.score, es.excused, es.created_at, es.updated_at
            FROM exam_scores es
            JOIN assessments a ON a.legacy_id = es.exam_id AND a.legacy_type = 'exam'
        ");

        // Step 5: Copy topic associations
        if (Schema::hasTable('test_topic') && Schema::hasTable('assessment_topic')) {
            DB::statement("
                INSERT IGNORE INTO assessment_topic (assessment_id, topic_id)
                SELECT a.id, tt.topic_id
                FROM test_topic tt
                JOIN assessments a ON a.legacy_id = tt.test_id AND a.legacy_type = 'test'
            ");
        }

        if (Schema::hasTable('exam_topic') && Schema::hasTable('assessment_topic')) {
            DB::statement("
                INSERT IGNORE INTO assessment_topic (assessment_id, topic_id)
                SELECT a.id, et.topic_id
                FROM exam_topic et
                JOIN assessments a ON a.legacy_id = et.exam_id AND a.legacy_type = 'exam'
            ");
        }
    }

    public function down(): void
    {
        // Clear migrated data
        DB::table('assessment_scores')->delete();
        DB::table('assessment_topic')->delete();
        DB::table('assessments')->delete();
        DB::table('assessment_types')->delete();

        // Remove legacy columns
        if (Schema::hasColumn('assessments', 'legacy_id')) {
            Schema::table('assessments', function (Blueprint $table) {
                $table->dropIndex(['legacy_id', 'legacy_type']);
                $table->dropColumn(['legacy_id', 'legacy_type']);
            });
        }
    }
};
