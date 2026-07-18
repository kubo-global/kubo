<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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
    }

    public function down(): void
    {
        Schema::dropIfExists('offering_subjects');
    }
};
