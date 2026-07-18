<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-(school, school year) NAT setup: the ministry can change which grades
        // sit it, the subjects, and the totals from one year to the next, so this is
        // not a static per-country constant. Carried forward on rollover.
        Schema::create('nat_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('schoolyear_id');
            $table->foreign('schoolyear_id')->references('id')->on('schoolyears')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->string('label')->default('National Assessment Test');
            $table->timestamps();
            $table->unique(['school_id', 'schoolyear_id']);
        });

        // The subject set + max score per grade that sits the NAT that year.
        Schema::create('nat_config_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nat_config_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('grade_id');
            $table->foreign('grade_id')->references('id')->on('grades');
            $table->unsignedInteger('subject_id');
            $table->foreign('subject_id')->references('id')->on('subjects');
            $table->unsignedInteger('max_score')->default(100);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nat_config_subjects');
        Schema::dropIfExists('nat_configs');
    }
};
