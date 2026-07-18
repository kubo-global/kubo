<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructional_hours', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('offering_id');
            $table->foreign('offering_id')->references('id')->on('offerings')->cascadeOnDelete();
            $table->date('date');
            // Expected hours are derived from the timetable; the teacher logs actual/lost.
            $table->decimal('actual_hours', 4, 2)->nullable();
            $table->decimal('lost_hours', 4, 2)->nullable();
            $table->string('remarks')->nullable();
            $table->unsignedInteger('recorded_by')->nullable();
            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['offering_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructional_hours');
    }
};
