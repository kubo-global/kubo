<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the abandoned `offering_subjects` / `student_subjects` machinery.
 *
 * These tables (2026-03) were a never-wired-up second attempt at the
 * per-class subject list, with electives (`is_elective`) and per-student
 * subject enrolment. The live app has always used `subject_term_offering`
 * (Offering::subjects() → scorebook, term reports, rollover, settings);
 * the DemoSeeder itself flags these as "abandoned legacy". Removing them
 * before rollout leaves a single, unambiguous source of truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        // student_subjects FKs into offering_subjects — drop the child first.
        Schema::dropIfExists('student_subjects');
        Schema::dropIfExists('offering_subjects');
    }

    public function down(): void
    {
        Schema::create('offering_subjects', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('offering_id');
            $table->foreign('offering_id')->references('id')->on('offerings');
            $table->unsignedInteger('subject_id');
            $table->foreign('subject_id')->references('id')->on('subjects');
            $table->unsignedInteger('term_id');
            $table->foreign('term_id')->references('id')->on('terms');
            $table->boolean('is_elective')->default(false);
            $table->timestamps();

            $table->unique(['offering_id', 'subject_id', 'term_id']);
        });

        Schema::create('student_subjects', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('enrollment_id');
            $table->foreign('enrollment_id')->references('id')->on('enrollments');
            $table->foreignId('offering_subject_id')->constrained('offering_subjects');
            $table->timestamps();

            $table->unique(['enrollment_id', 'offering_subject_id']);
        });
    }
};
