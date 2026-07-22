<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional per-class "core subject" flag. When a class/term has any subjects
 * flagged core, the result ANALYSIS and histogram cover only those (a school's
 * promotion analysis runs on core subjects); the result sheet, totals and
 * positions are untouched. With no flags set, nothing changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subject_term_offering', function (Blueprint $table) {
            $table->boolean('core')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('subject_term_offering', function (Blueprint $table) {
            $table->dropColumn('core');
        });
    }
};
