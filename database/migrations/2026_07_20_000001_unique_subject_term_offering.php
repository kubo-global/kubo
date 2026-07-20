<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A subject can only be attached to a class once per term. Duplicate pivot rows
 * made the subject render twice in the grid and on reports (seen in production:
 * Reading attached twice to a class's term). Clean up any existing duplicates,
 * then make the constraint structural.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Keep the lowest id of each (subject, term, offering) set, drop the rest.
        $dupes = DB::table('subject_term_offering')
            ->select('subject_id', 'term_id', 'offering_id', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as n'))
            ->groupBy('subject_id', 'term_id', 'offering_id')
            ->having('n', '>', 1)
            ->get();

        foreach ($dupes as $d) {
            DB::table('subject_term_offering')
                ->where('subject_id', $d->subject_id)
                ->where('term_id', $d->term_id)
                ->where('offering_id', $d->offering_id)
                ->where('id', '!=', $d->keep_id)
                ->delete();
        }

        Schema::table('subject_term_offering', function (Blueprint $table) {
            $table->unique(['subject_id', 'term_id', 'offering_id'], 'subject_term_offering_unique');
        });
    }

    public function down(): void
    {
        Schema::table('subject_term_offering', function (Blueprint $table) {
            $table->dropUnique('subject_term_offering_unique');
        });
    }
};
