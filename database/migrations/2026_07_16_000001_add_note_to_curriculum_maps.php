<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A free-text caveat on a piece of mapped content, e.g. "uses the American
 * unit system" or "British spelling". It describes the content itself, so it
 * lives on the curriculum_maps row and travels with the item wherever it is
 * mapped — the next person editing the curriculum sees what does not quite fit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curriculum_maps', function (Blueprint $table) {
            $table->string('note')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('curriculum_maps', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
