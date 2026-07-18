<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configurable employment-status list for staff (e.g. DHMC, HMA, SMC, QT,
     * SMB, ECD). Managed in Settings; referenced by staff_profiles.
     */
    public function up(): void
    {
        Schema::create('staff_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->nullable();
            $table->string('label', 20);
            $table->string('description')->nullable();
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_statuses');
    }
};
