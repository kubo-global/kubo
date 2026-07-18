<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A National Assessment Test is a one-off national exam, not part of a
        // school term, so its assessment carries no term. Allow a null term_id.
        Schema::table('assessments', function (Blueprint $table) {
            $table->unsignedInteger('term_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->unsignedInteger('term_id')->nullable(false)->change();
        });
    }
};
