<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grading_scales', function (Blueprint $table) {
            // null = term-report letter grades (existing behaviour);
            // 'nat' = National Assessment Test fail/pass/mastery bands.
            $table->string('purpose')->nullable()->after('school_id');
        });
    }

    public function down(): void
    {
        Schema::table('grading_scales', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
};
