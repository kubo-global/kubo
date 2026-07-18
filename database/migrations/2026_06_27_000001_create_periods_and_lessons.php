<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // School-wide period structure (the rows of every class timetable).
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('label');                 // "Period 1", "Break", "Lunch"
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_break')->default(false); // break rows hold no lessons
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        // One lesson = a single cell in a class timetable (day × period).
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('offering_id');
            $table->foreign('offering_id')->references('id')->on('offerings')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day'); // 1 = Monday … 5 = Friday
            $table->unsignedInteger('subject_id')->nullable();
            $table->foreign('subject_id')->references('id')->on('subjects')->nullOnDelete();
            $table->unsignedInteger('teacher_id')->nullable();
            $table->foreign('teacher_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['offering_id', 'day', 'period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('periods');
    }
};
