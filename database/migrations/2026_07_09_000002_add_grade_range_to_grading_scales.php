<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grade-band grading scales. A grading scale band can apply to a range of grades
 * (e.g. Grade 1-3 vs 4-6, which use different thresholds). Null range = the default
 * set, used for any grade not covered by a specific range, so existing single-scale
 * schools are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grading_scales', function (Blueprint $table) {
            $table->unsignedInteger('grade_min')->nullable()->after('purpose');
            $table->unsignedInteger('grade_max')->nullable()->after('grade_min');
        });
    }

    public function down(): void
    {
        Schema::table('grading_scales', function (Blueprint $table) {
            $table->dropColumn(['grade_min', 'grade_max']);
        });
    }
};
