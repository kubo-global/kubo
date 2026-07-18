<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_subjects', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('enrollment_id');
            $table->foreign('enrollment_id')->references('id')->on('enrollments');
            $table->foreignId('offering_subject_id')->constrained('offering_subjects');
            $table->timestamps();

            $table->unique(['enrollment_id', 'offering_subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_subjects');
    }
};
