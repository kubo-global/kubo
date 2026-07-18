<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `excused` flag on assessment_scores always meant "absent" — a blank score
 * that counts as 0. Rename it to match its behaviour. (Attendance keeps a separate,
 * genuine `excused` status; this only touches assessment scores.)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('assessment_scores', 'excused') && ! Schema::hasColumn('assessment_scores', 'absent')) {
            Schema::table('assessment_scores', function (Blueprint $table) {
                $table->renameColumn('excused', 'absent');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('assessment_scores', 'absent') && ! Schema::hasColumn('assessment_scores', 'excused')) {
            Schema::table('assessment_scores', function (Blueprint $table) {
                $table->renameColumn('absent', 'excused');
            });
        }
    }
};
