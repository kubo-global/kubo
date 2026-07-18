<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three new health record types alongside the existing health_reports
 * (which keeps doing periodic checkups + growth measurements):
 *
 *   incident_reports     acute events: when, where, what happened, action
 *   wound_cases          one wound = one case; admin closes when healed
 *     wound_care_visits  many visits per case (treatment log)
 *   medical_notes        free-form notes — catch-all for everything else
 *
 * All three render on the student's Health tab as cards in a unified
 * chronological timeline.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('incident_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('recorded_by')->nullable();
            $table->dateTime('occurred_at');
            $table->string('location')->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->text('complaint');
            $table->text('action_taken')->nullable();
            $table->boolean('first_aid_given')->default(false);
            $table->boolean('sent_home')->default(false);
            $table->boolean('taken_to_hospital')->default(false);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('recorded_by')->references('id')->on('users');
            $table->index(['user_id', 'occurred_at']);
        });

        Schema::create('wound_cases', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->date('opened_on');
            $table->text('diagnosis');
            $table->date('closed_on')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users');
            $table->index(['user_id', 'opened_on']);
        });

        Schema::create('wound_care_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wound_case_id');
            $table->unsignedInteger('recorded_by')->nullable();
            $table->date('visited_on');
            $table->text('treatment');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->foreign('wound_case_id')->references('id')->on('wound_cases')->cascadeOnDelete();
            $table->foreign('recorded_by')->references('id')->on('users');
            $table->index(['wound_case_id', 'visited_on']);
        });

        Schema::create('medical_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('recorded_by')->nullable();
            $table->date('noted_on');
            $table->text('content');
            $table->string('location')->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('recorded_by')->references('id')->on('users');
            $table->index(['user_id', 'noted_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_notes');
        Schema::dropIfExists('wound_care_visits');
        Schema::dropIfExists('wound_cases');
        Schema::dropIfExists('incident_reports');
    }
};
