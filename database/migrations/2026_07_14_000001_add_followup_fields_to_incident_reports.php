<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incident_reports', function (Blueprint $table) {
            $table->string('medication_given')->nullable()->after('first_aid_given');
            $table->boolean('parents_contacted')->default(false)->after('medication_given');
            // Null = still open, needs follow-up. Mirrors wound_cases.closed_on.
            $table->date('closed_on')->nullable()->after('taken_to_hospital');
        });

        // Everything logged before this migration is history, not a live worklist:
        // close it on the day it happened so the open list starts empty.
        DB::table('incident_reports')->update(['closed_on' => DB::raw('date(occurred_at)')]);
    }

    public function down(): void
    {
        Schema::table('incident_reports', function (Blueprint $table) {
            $table->dropColumn(['medication_given', 'parents_contacted', 'closed_on']);
        });
    }
};
