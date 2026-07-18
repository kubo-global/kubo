<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_health_milestones', function (Blueprint $table) {
            $table->id();
            // users.id is int(10) unsigned in legacy schema — match it.
            $table->unsignedInteger('user_id')->unique();
            $table->date('first_menstruated_on')->nullable();
            $table->date('hep_a_received_on')->nullable();
            $table->date('polio_received_on')->nullable();
            $table->date('tetanus_received_on')->nullable();
            $table->date('yellow_fever_received_on')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Backfill from existing health_reports: for each "once-true" field,
        // take the earliest report where the flag was set and use that
        // report's created_at as the milestone date.
        $rows = DB::select("
            SELECT user_id,
                MIN(CASE WHEN already_menstruated = 1 THEN DATE(created_at) END) AS first_menstruated_on,
                MIN(CASE WHEN hepatitis_a_vaccine_received = 1 THEN DATE(created_at) END) AS hep_a_received_on,
                MIN(CASE WHEN poliomyelitis_vaccine_received = 1 THEN DATE(created_at) END) AS polio_received_on,
                MIN(CASE WHEN tetanus_vaccine_received = 1 THEN DATE(created_at) END) AS tetanus_received_on,
                MIN(CASE WHEN yellow_fever_vaccine_received = 1 THEN DATE(created_at) END) AS yellow_fever_received_on
            FROM health_reports
            GROUP BY user_id
            HAVING first_menstruated_on IS NOT NULL
                OR hep_a_received_on IS NOT NULL
                OR polio_received_on IS NOT NULL
                OR tetanus_received_on IS NOT NULL
                OR yellow_fever_received_on IS NOT NULL
        ");

        $now = now();
        foreach ($rows as $row) {
            DB::table('student_health_milestones')->insert([
                'user_id' => $row->user_id,
                'first_menstruated_on' => $row->first_menstruated_on,
                'hep_a_received_on' => $row->hep_a_received_on,
                'polio_received_on' => $row->polio_received_on,
                'tetanus_received_on' => $row->tetanus_received_on,
                'yellow_fever_received_on' => $row->yellow_fever_received_on,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_health_milestones');
    }
};
