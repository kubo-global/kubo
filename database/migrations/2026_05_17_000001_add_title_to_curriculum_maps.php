<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The exercise library at /content shows mapped Kolibri exercises but
 * had no way to display their names — curriculum_maps only stored
 * Kolibri node ids, so the UI fell back to the literal word "Exercise"
 * on every row. Add a title column so the mapping form's title input
 * (already submitted) actually gets persisted.
 *
 * Existing rows backfill via `php artisan kolibri:backfill-map-titles`.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('curriculum_maps', function (Blueprint $table) {
            $table->string('title')->nullable()->after('content_kind');
        });
    }

    public function down(): void
    {
        Schema::table('curriculum_maps', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
