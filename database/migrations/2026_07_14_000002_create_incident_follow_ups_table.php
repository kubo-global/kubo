<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An open incident says "needs follow-up" but had nowhere to record the
 * follow-up itself: you could only close it or edit the original entry. Same
 * shape as wound_care_visits: one row per time somebody checked back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_follow_ups', function (Blueprint $table) {
            $table->id();
            // users.id is an unsignedInteger here, not a bigint: match it, as the
            // other health tables do, or the foreign key is rejected.
            $table->unsignedBigInteger('incident_report_id');
            $table->unsignedInteger('recorded_by')->nullable();
            $table->date('noted_on');
            $table->text('note');
            $table->timestamps();

            $table->foreign('incident_report_id')->references('id')->on('incident_reports')->cascadeOnDelete();
            $table->foreign('recorded_by')->references('id')->on('users');
            $table->index(['incident_report_id', 'noted_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_follow_ups');
    }
};
