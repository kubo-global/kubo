<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('lesson_plans')) {
            return; // already created (legacy environments where the table existed before this migration was added)
        }

        Schema::create('lesson_plans', function (Blueprint $table) {
            $table->id();
            // The legacy schema's parent tables (users, offerings, subjects) use
            // int(10) unsigned for their primary keys, not bigint. Match that
            // so the foreign keys can be created.
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('offering_id');
            $table->unsignedInteger('subject_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('offering_id')->references('id')->on('offerings');
            $table->foreign('subject_id')->references('id')->on('subjects');
            $table->date('lesson_date');
            $table->string('topic');
            $table->text('content')->nullable();
            $table->text('objectives')->nullable();
            $table->text('resources')->nullable();
            $table->text('activities')->nullable();
            $table->text('assessment')->nullable();
            $table->text('conclusion')->nullable();
            $table->text('assistant_coordinator_remarks')->nullable();
            $table->text('coordinator_remarks')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'lesson_date']);
            $table->index(['offering_id', 'lesson_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_plans');
    }
};
