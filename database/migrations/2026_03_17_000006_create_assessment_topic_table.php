<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_topic', function (Blueprint $table) {
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('topic_id');
            $table->foreign('topic_id')->references('id')->on('topics')->cascadeOnDelete();

            $table->primary(['assessment_id', 'topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_topic');
    }
};
