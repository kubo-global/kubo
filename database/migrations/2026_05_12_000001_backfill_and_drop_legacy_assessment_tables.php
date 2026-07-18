<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the legacy tests / exams / *_scores / *_topic tables.
 *
 * Before dropping, idempotently copy any rows that haven't already been
 * mirrored into the unified assessments table. This is the same logic the
 * `assessments:backfill` artisan command ran ad-hoc — that command is
 * deleted in this same change, since the legacy tables it reads from go
 * away here.
 *
 * Safe to run when:
 *  - tables already dropped (no-op)
 *  - tables exist but are empty (still drops)
 *  - tables exist with un-backfilled data (backfills first, then drops)
 */
return new class extends Migration {
    public function up(): void
    {
        $hasLegacy = Schema::hasTable('tests') && Schema::hasTable('exams');

        if ($hasLegacy) {
            $this->backfill();
        }

        Schema::dropIfExists('test_scores');
        Schema::dropIfExists('exam_scores');
        Schema::dropIfExists('test_topic');
        Schema::dropIfExists('exam_topic');
        Schema::dropIfExists('tests');
        Schema::dropIfExists('exams');

        // The legacy_id / legacy_type columns were temporary tracking fields
        // added by the 2026_03_31 migration to map legacy rows during the
        // backfill. Now that the legacy tables are gone, these columns are
        // cleanup debris — drop them so the schema is uniform across all
        // install paths (some fresh installs never had them).
        if (Schema::hasColumn('assessments', 'legacy_id')) {
            Schema::table('assessments', function (Blueprint $table) {
                $table->dropIndex(['legacy_id', 'legacy_type']);
                $table->dropColumn(['legacy_id', 'legacy_type']);
            });
        }
    }

    public function down(): void
    {
        // The legacy schema is no longer maintained — rolling back is not
        // supported. Restore from a backup if you need the old tables.
        throw new \RuntimeException('Rollback unsupported. Restore from a database backup if you need the legacy tables.');
    }

    private function backfill(): void
    {
        $school = DB::table('schools')->first();
        if (!$school) {
            // No school = no rows to backfill against. Skip silently; the
            // drops below will still run on empty legacy tables.
            return;
        }

        $testTypeId = $this->resolveAssessmentType($school->id, 'Test', 0.25, 1);
        $examTypeId = $this->resolveAssessmentType($school->id, 'Exam', 0.75, 2);

        // Ensure the legacy tracking columns exist before we INSERT against
        // them. The original 2026_03_31 migration adds these, but it
        // short-circuits when no school row exists at run time — so on
        // production installs (which had no schools row when that migration
        // ran), assessments doesn't have these columns yet.
        if (!Schema::hasColumn('assessments', 'legacy_id')) {
            Schema::table('assessments', function (Blueprint $table) {
                $table->unsignedInteger('legacy_id')->nullable()->after('id');
                $table->string('legacy_type', 10)->nullable()->after('legacy_id');
                $table->index(['legacy_id', 'legacy_type']);
            });
        }

        $this->copyLegacyToAssessments('tests', 'test', $testTypeId);
        $this->copyLegacyToAssessments('exams', 'exam', $examTypeId);

        $this->copyLegacyScores('test_scores', 'test_id', 'test');
        $this->copyLegacyScores('exam_scores', 'exam_id', 'exam');

        $this->copyTopicAssociations('test_topic', 'test_id', 'test');
        $this->copyTopicAssociations('exam_topic', 'exam_id', 'exam');
    }

    private function resolveAssessmentType(int $schoolId, string $name, float $defaultWeight, int $order): int
    {
        $existing = DB::table('assessment_types')->where('school_id', $schoolId)->where('name', $name)->first();
        if ($existing) {
            return $existing->id;
        }

        $weight = (float) (DB::table('school_parameters')
            ->where('key', $name === 'Test' ? 'testWeight' : 'examWeight')
            ->value('value') ?? $defaultWeight);

        return DB::table('assessment_types')->insertGetId([
            'school_id' => $schoolId,
            'name' => $name,
            'weight' => $weight,
            'display_order' => $order,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function copyLegacyToAssessments(string $legacyTable, string $legacyType, int $typeId): void
    {
        DB::affectingStatement("
            INSERT INTO assessments (legacy_id, legacy_type, assessment_type_id, subject_id, offering_id, term_id, date, name, info, max_score, confirmed, created_at, updated_at)
            SELECT t.id, ?, ?, t.subject_id, t.offering_id, t.term_id, t.date, t.name, t.info, t.max_score, t.confirmed, t.created_at, t.updated_at
            FROM {$legacyTable} t
            LEFT JOIN assessments a ON a.legacy_id = t.id AND a.legacy_type = ?
            WHERE a.id IS NULL
        ", [$legacyType, $typeId, $legacyType]);
    }

    private function copyLegacyScores(string $scoreTable, string $foreignKey, string $legacyType): void
    {
        if (!Schema::hasTable($scoreTable)) {
            return;
        }
        DB::affectingStatement("
            INSERT INTO assessment_scores (user_id, assessment_id, score, excused, created_at, updated_at)
            SELECT s.user_id, a.id, s.score, s.excused, s.created_at, s.updated_at
            FROM {$scoreTable} s
            JOIN assessments a ON a.legacy_id = s.{$foreignKey} AND a.legacy_type = ?
            LEFT JOIN assessment_scores existing ON existing.user_id = s.user_id AND existing.assessment_id = a.id
            WHERE existing.user_id IS NULL
        ", [$legacyType]);
    }

    private function copyTopicAssociations(string $pivotTable, string $foreignKey, string $legacyType): void
    {
        if (!Schema::hasTable($pivotTable) || !Schema::hasTable('assessment_topic')) {
            return;
        }
        DB::affectingStatement("
            INSERT INTO assessment_topic (assessment_id, topic_id)
            SELECT a.id, t.topic_id
            FROM {$pivotTable} t
            JOIN assessments a ON a.legacy_id = t.{$foreignKey} AND a.legacy_type = ?
            LEFT JOIN assessment_topic existing ON existing.assessment_id = a.id AND existing.topic_id = t.topic_id
            WHERE existing.assessment_id IS NULL
        ", [$legacyType]);
    }
};
