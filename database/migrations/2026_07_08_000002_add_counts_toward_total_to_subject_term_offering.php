<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-class/term override for "counts toward total".
 *
 * The school-wide default lives on `subjects.counts_toward_total`. This
 * nullable pivot column lets a single (class, term) mapping override it:
 *   null       → inherit the subject's school-wide default
 *   true/false → this subject counts (or not) toward the total for THIS
 *                class and term only.
 * Resolved in Subject::countsTowardTotalResolved().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subject_term_offering', function (Blueprint $table) {
            $table->boolean('counts_toward_total')->nullable()->default(null)->after('offering_id');
        });
    }

    public function down(): void
    {
        Schema::table('subject_term_offering', function (Blueprint $table) {
            $table->dropColumn('counts_toward_total');
        });
    }
};
