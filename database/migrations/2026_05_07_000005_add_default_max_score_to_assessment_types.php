<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('assessment_types', 'default_max_score')) {
            return;
        }

        Schema::table('assessment_types', function (Blueprint $table) {
            $table->unsignedInteger('default_max_score')->nullable()->after('weight');
        });

        // Sensible defaults for the existing two types if present.
        DB::table('assessment_types')->where('name', 'Test')->update(['default_max_score' => 20]);
        DB::table('assessment_types')->where('name', 'Exam')->update(['default_max_score' => 100]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('assessment_types', 'default_max_score')) {
            Schema::table('assessment_types', function (Blueprint $table) {
                $table->dropColumn('default_max_score');
            });
        }
    }
};
