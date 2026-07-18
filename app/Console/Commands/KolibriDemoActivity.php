<?php

namespace App\Console\Commands;

use App\Domain\Learning\Models\ExerciseRun;
use App\Domain\Learning\Models\Skill;
use App\Domain\Learning\Models\StudentSkill;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use KuboKolibri\Client\KolibriClient;
use KuboKolibri\Services\ExerciseRunService;
use KuboKolibri\Services\KolibriProvisioner;

/**
 * Turn the demo's seeded skill mastery into genuinely round-tripped progress.
 *
 * The seeder plants StudentSkill/ExerciseRun rows so the dashboards are populated
 * on any install, including local ones with no Kolibri. This command — run by
 * demo:reset AFTER learners are provisioned in Kolibri — replays those mastered
 * skills as REAL exercise completions: it drives Kolibri's own progress-tracking
 * API as the pupil, then lets the normal completion path read the resulting
 * attempt logs back and record the mastery. So the numbers a visitor (or Learning
 * Equality) sees came through Kolibri, not straight into KUBO's tables.
 *
 * DEMO_MODE only, and non-fatal per pupil/skill: if Kolibri is unreachable or its
 * logging API shifts, the planted baseline stands and the demo still works.
 */
class KolibriDemoActivity extends Command
{
    protected $signature = 'kolibri:demo-activity {--limit=3 : Pupils per class to make real}';

    protected $description = 'Replay seeded Learn mastery as real Kolibri exercise completions (DEMO_MODE only)';

    public function handle(): int
    {
        if (! config('app.demo')) {
            $this->error('DEMO_MODE is off — refusing to drive Kolibri activity.');

            return self::FAILURE;
        }
        if (! config('kubo-kolibri.kolibri_url')) {
            $this->warn('Kolibri is not configured — leaving the seeded baseline as is.');

            return self::SUCCESS;
        }

        // Resolved only past the guards: constructing KolibriClient requires a
        // configured URL, so method injection would crash on unconfigured installs
        // before the friendly warning above could run.
        $client = app(KolibriClient::class);
        $provisioner = app(KolibriProvisioner::class);
        $runService = app(ExerciseRunService::class);

        $facilityId = \App\Models\School::whereNotNull('kolibri_facility_id')->value('kolibri_facility_id');
        if (! $facilityId) {
            $this->warn('No Kolibri facility — run kolibri:provision first. Leaving the baseline.');

            return self::SUCCESS;
        }

        // The pupils the seeder gave mastery to: those with a mastered StudentSkill.
        $pupils = User::whereNotNull('kolibri_user_id')
            ->whereIn('id', StudentSkill::where('status', 'mastered')->select('user_id'))
            ->orderBy('id')
            ->get();

        // The seeder now gives the WHOLE class a baseline, but driving real Kolibri
        // completions for every pupil is slow and needless. Cap the round-trip to a
        // few pupils per class; the rest keep their seeded baseline, which already
        // looks the part on the dashboard.
        $limit = max(0, (int) $this->option('limit'));
        if ($limit > 0) {
            $pupils = $this->capPerClass($pupils, $limit);
        }

        $real = 0;
        $failed = 0;
        $nodeCache = [];

        foreach ($pupils as $pupil) {
            $session = null; // opened lazily, once per pupil

            $mastered = StudentSkill::where('user_id', $pupil->id)->where('status', 'mastered')
                ->pluck('skill_id');

            foreach (Skill::whereIn('id', $mastered)->get() as $skill) {
                $map = $skill->content()->wherePivot('role', 'practice')->first();
                if (! $map) {
                    continue;
                }

                // Node details (content id, channel, mastery model, items) — cached across pupils.
                if (! isset($nodeCache[$map->kolibri_node_id])) {
                    $node = $client->getContentNode($map->kolibri_node_id);
                    $meta = $node['assessmentmetadata'] ?? [];
                    $nodeCache[$map->kolibri_node_id] = ($node && ! empty($meta['mastery_model']) && ! empty($meta['assessment_item_ids'])) ? [
                        'content_id' => $node['content_id'],
                        'channel_id' => $map->kolibri_channel_id,
                        'mastery_model' => $meta['mastery_model'],
                        'items' => $meta['assessment_item_ids'],
                    ] : null;
                }
                $nc = $nodeCache[$map->kolibri_node_id];
                if (! $nc) {
                    continue; // not an assessable exercise node — leave its planted mastery
                }

                $session ??= $client->openSession(
                    $provisioner->kolibriUsername($pupil),
                    $provisioner->kolibriPassword($pupil),
                    $facilityId,
                );
                if (! $session) {
                    $failed++;
                    break; // can't reach this pupil's session; keep their baseline, move on
                }

                // A believable pass: enough correct to meet mastery, plus a varying
                // number of earlier misses so scores spread across pupils (a whole
                // class at 100% looks planted) instead of all landing on the same mark.
                $need = (int) ($nc['mastery_model']['m'] ?? 5);
                $correct = min($need, count($nc['items']));
                $wrong = min(($pupil->id + $skill->id) % 3, max(0, count($nc['items']) - $correct));

                $ok = $client->recordExerciseCompletion(
                    $session, $map->kolibri_node_id, $nc['content_id'], $nc['channel_id'],
                    $nc['mastery_model'], $nc['items'], $correct, $wrong,
                );
                if (! $ok) {
                    $failed++;

                    continue;
                }

                // Read the real attempts back through the normal completion path, so the
                // mastery is computed exactly as it is for a live pupil.
                $run = ExerciseRun::create([
                    'user_id' => $pupil->id,
                    'skill_id' => $skill->id,
                    'curriculum_map_id' => $map->id,
                    'mode' => 'free',
                    'status' => 'active',
                    'started_at' => Carbon::now()->subMinutes(3),
                ]);
                $runService->completeRun($run, $pupil, $skill);
                $real++;
            }
        }

        $this->info("Kolibri demo activity: {$real} real completion(s) recorded".($failed ? ", {$failed} fell back to baseline." : '.'));

        return self::SUCCESS;
    }

    /**
     * Keep at most $limit pupils per class (current school year's offering), so the
     * expensive Kolibri round-trip stays small even when the whole class has a
     * seeded baseline. Pupils outside any current offering are kept as one group.
     */
    private function capPerClass(\Illuminate\Support\Collection $pupils, int $limit): \Illuminate\Support\Collection
    {
        $year = Schoolyear::current() ?? Schoolyear::orderByDesc('id')->first();
        $offeringByPupil = $year
            ? Enrollment::whereIn('user_id', $pupils->pluck('id'))
                ->whereIn('offering_id', Offering::where('schoolyear_id', $year->id)->select('id'))
                ->pluck('offering_id', 'user_id')
            : collect();

        $perClass = [];

        return $pupils->filter(function ($pupil) use (&$perClass, $offeringByPupil, $limit) {
            $offering = $offeringByPupil[$pupil->id] ?? 0;
            $perClass[$offering] = ($perClass[$offering] ?? 0) + 1;

            return $perClass[$offering] <= $limit;
        })->values();
    }
}
