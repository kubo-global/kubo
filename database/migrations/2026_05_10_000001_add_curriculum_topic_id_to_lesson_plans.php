<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lesson_plans', function (Blueprint $table) {
            // Optional link to a curriculum topic. When set, the lesson plan
            // "unlocks" practice content tagged to this topic for the class —
            // gated on the school config `gate_exercises_by_lesson_plan`.
            $table->unsignedInteger('curriculum_topic_id')->nullable()->after('topic');
            $table->foreign('curriculum_topic_id')->references('id')->on('topics');
            $table->index('curriculum_topic_id');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_plans', function (Blueprint $table) {
            $table->dropForeign(['curriculum_topic_id']);
            $table->dropIndex(['curriculum_topic_id']);
            $table->dropColumn('curriculum_topic_id');
        });
    }
};
