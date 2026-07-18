<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sign-off is separate from remarks. A coordinator or assistant coordinator can
 * leave remarks without signing off, and signing off is an explicit action. These
 * nullable timestamps record when each level signed off (null = not signed off).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_plans', function (Blueprint $table) {
            $table->timestamp('assistant_coordinator_signed_at')->nullable()->after('assistant_coordinator_remarks');
            $table->timestamp('coordinator_signed_at')->nullable()->after('coordinator_remarks');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_plans', function (Blueprint $table) {
            $table->dropColumn(['assistant_coordinator_signed_at', 'coordinator_signed_at']);
        });
    }
};
