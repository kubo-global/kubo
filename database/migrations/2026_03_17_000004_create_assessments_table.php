<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('subject_id');
            $table->foreign('subject_id')->references('id')->on('subjects');
            $table->unsignedInteger('offering_id');
            $table->foreign('offering_id')->references('id')->on('offerings');
            $table->unsignedInteger('term_id');
            $table->foreign('term_id')->references('id')->on('terms');
            $table->date('date')->nullable();
            $table->string('name');
            $table->string('info')->nullable();
            $table->unsignedInteger('max_score');
            $table->boolean('confirmed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
