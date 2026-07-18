<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('health_reports', function (Blueprint $table) {
            $table->dropColumn([
                'already_menstruated',
                'hepatitis_a_vaccine_received',
                'poliomyelitis_vaccine_received',
                'tetanus_vaccine_received',
                'yellow_fever_vaccine_received',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('health_reports', function (Blueprint $table) {
            $table->boolean('already_menstruated')->nullable();
            $table->boolean('hepatitis_a_vaccine_received')->nullable();
            $table->boolean('poliomyelitis_vaccine_received')->nullable();
            $table->boolean('tetanus_vaccine_received')->nullable();
            $table->boolean('yellow_fever_vaccine_received')->nullable();
        });
    }
};
