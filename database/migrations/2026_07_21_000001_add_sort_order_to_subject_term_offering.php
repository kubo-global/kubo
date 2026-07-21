<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional explicit subject order per class/term. When set, the scorebook and
 * its printouts show columns in this order (a school's paper sheet order);
 * when null, everything keeps the historic subject-id order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subject_term_offering', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->nullable()->after('offering_id');
        });
    }

    public function down(): void
    {
        Schema::table('subject_term_offering', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
