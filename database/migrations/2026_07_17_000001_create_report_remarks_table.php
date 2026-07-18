<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-pupil, per-term free-text feedback for the report card — the bits a rubric
 * can't compute: conduct and the general remark. Filled on the "Prepare reports"
 * screen and printed on the card, replacing the hand-written blanks. One row per
 * (enrollment, term).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_remarks', function (Blueprint $table) {
            $table->id();
            // enrollments.id and terms.id are legacy int-unsigned, so match that type.
            $table->unsignedInteger('enrollment_id');
            $table->unsignedInteger('term_id');
            $table->string('conduct')->nullable();
            $table->text('general_remarks')->nullable();
            $table->timestamps();

            $table->foreign('enrollment_id')->references('id')->on('enrollments')->cascadeOnDelete();
            $table->foreign('term_id')->references('id')->on('terms')->cascadeOnDelete();
            $table->unique(['enrollment_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_remarks');
    }
};
