<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff-only employment record — kept off the shared `profiles` table so
     * student rows never carry staff HR columns. Gender and phone stay on
     * `profiles` (they apply to everyone); this holds the employment facts.
     */
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            // users.id is a legacy INT (increments), so match it rather than foreignId()'s bigint.
            $table->unsignedInteger('user_id')->unique();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('prn', 40)->nullable();          // Personnel Record Number
            $table->string('tin', 40)->nullable();          // Tax Identification Number
            $table->foreignId('staff_status_id')->nullable()->constrained('staff_statuses')->nullOnDelete();
            $table->date('appointed_on')->nullable();       // date of appointment
            $table->date('confirmed_on')->nullable();       // date of confirmation
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
    }
};
