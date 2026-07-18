<?php

namespace App\Console\Commands;

use App\Models\Subject;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Merge one subject into another. Used to clean up near-duplicate
 * subject rows (e.g. "S.E.S" vs "S.E.S.") created by repeated seeding.
 * Re-points every FK reference from the source subject to the target,
 * then deletes the source.
 *
 *   php artisan subjects:merge <source_id> <target_id>            # dry-run
 *   php artisan subjects:merge <source_id> <target_id> --apply    # commit
 *
 * Handles uniqueness on the subject_term_offering pivot — if the
 * target already has a row for the same (term, offering), the source's
 * row is dropped rather than triggering a constraint violation.
 */
class MergeSubjects extends Command
{
    protected $signature = 'subjects:merge
        {source : Subject id to absorb (will be deleted)}
        {target : Subject id to keep}
        {--apply : Actually write (default is dry-run)}';

    protected $description = 'Merge a duplicate subject into another, re-pointing every FK then deleting the source.';

    public function handle(): int
    {
        $sourceId = (int) $this->argument('source');
        $targetId = (int) $this->argument('target');

        if ($sourceId === $targetId) {
            $this->error('Source and target are the same.');
            return self::FAILURE;
        }

        $source = Subject::find($sourceId);
        $target = Subject::find($targetId);

        if (!$source || !$target) {
            $this->error("Source or target subject not found.");
            return self::FAILURE;
        }

        $this->line("Source: [{$source->id}] {$source->name}");
        $this->line("Target: [{$target->id}] {$target->name}");
        $this->newLine();

        // Tables with a plain subject_id column: re-point directly.
        $simpleTables = [
            'assessments',
            'authored_exercises',
            'curriculum_maps',
            'lesson_plans',
            'skills',
            'teacher_assignments',
            'topics',
        ];

        $counts = [];
        foreach ($simpleTables as $table) {
            $counts[$table] = DB::table($table)->where('subject_id', $sourceId)->count();
        }
        $counts['subject_term_offering'] = DB::table('subject_term_offering')->where('subject_id', $sourceId)->count();

        $this->info('Rows to re-point:');
        foreach ($counts as $table => $n) {
            $this->line("  {$table}: {$n}");
        }

        if (!$this->option('apply')) {
            $this->newLine();
            $this->warn('Dry-run. Re-run with --apply to commit.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($sourceId, $targetId, $simpleTables) {
            foreach ($simpleTables as $table) {
                DB::table($table)->where('subject_id', $sourceId)->update(['subject_id' => $targetId]);
            }

            // subject_term_offering has a (subject_id, term_id, offering_id) PK.
            // For each source row, only re-point if the target doesn't
            // already have the (term, offering) — else drop the duplicate.
            $rows = DB::table('subject_term_offering')->where('subject_id', $sourceId)->get();
            foreach ($rows as $row) {
                $exists = DB::table('subject_term_offering')
                    ->where('subject_id', $targetId)
                    ->where('term_id', $row->term_id)
                    ->where('offering_id', $row->offering_id)
                    ->exists();

                if ($exists) {
                    DB::table('subject_term_offering')
                        ->where('subject_id', $sourceId)
                        ->where('term_id', $row->term_id)
                        ->where('offering_id', $row->offering_id)
                        ->delete();
                } else {
                    DB::table('subject_term_offering')
                        ->where('subject_id', $sourceId)
                        ->where('term_id', $row->term_id)
                        ->where('offering_id', $row->offering_id)
                        ->update(['subject_id' => $targetId]);
                }
            }

            DB::table('subjects')->where('id', $sourceId)->delete();
        });

        $this->info("Merged. Subject {$sourceId} deleted; all references now point to {$targetId}.");
        return self::SUCCESS;
    }
}
