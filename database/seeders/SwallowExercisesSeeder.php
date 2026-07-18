<?php

namespace Database\Seeders;

use App\Domain\Learning\Models\ExerciseRun;
use App\Domain\Learning\Models\LessonAssignment;
use App\Domain\Learning\Models\Skill;
use App\Domain\Learning\Models\StudentSkill;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Offering;
use App\Models\School;
use App\Models\SchoolConfig;
use App\Models\Schoolyear;
use App\Models\Subject;
use App\Modules\Registry;
use App\Support\SchoolCalendar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use KuboKolibri\Services\CurriculumInstaller;

/**
 * Learn / exercises for the demo.
 *
 * Installs the real curriculum authored at The Swallow — the Gambia Lower Basic
 * mathematics skill graph and its Kolibri mappings (bundled in the kubo-kolibri
 * package as `gambia-mathematics`), covering Grade 1 to Grade 6 — and gives the
 * first class of a few grades a believable starting point so the pupil dashboard
 * demoes well.
 *
 * Learn starts at Grade 1: a real school doesn't run digital exercises for the
 * nursery years. The dashboard (skills, prerequisites, progress, assigned lesson)
 * is pure DB and shows immediately; launching an exercise additionally needs a
 * running Kolibri with this curriculum's channel imported and the pupils mapped
 * to Kolibri learners (the demo box has both; `demo:reset` re-provisions them).
 */
class SwallowExercisesSeeder extends Seeder
{
    private const CURRICULUM = 'gambia-mathematics';

    /** Grades to give a class real progress + this week's homework. */
    private const DEMO_GRADES = ['Grade 1', 'Grade 2', 'Grade 3'];

    public function run(CurriculumInstaller $installer): void
    {
        $school = School::first();
        if (! $school) {
            $this->command?->warn('No school found — seed the school first.');

            return;
        }

        // The installer maps skills onto the school's own Mathematics subject.
        Subject::firstOrCreate(['name' => 'Mathematics', 'school_id' => $school->id]);

        // Turn Learn + Library on for this school (default-off modules).
        $modules = collect(Registry::enabledList())->merge(['learn', 'library'])->unique()->values()->all();
        $school->configs()->updateOrCreate(['key' => SchoolConfig::ENABLED_MODULES], ['value' => $modules]);

        $data = $installer->load(self::CURRICULUM);
        if (! $data) {
            $this->command?->warn("Curriculum '".self::CURRICULUM."' not found — skipping Learn seed.");

            return;
        }
        $stats = $installer->install($data);

        $this->seedProgress($school);

        $this->command?->info(sprintf(
            'Learn: installed %s (%d skills, %d created); progress for %s.',
            $data['name'],
            count($data['skills']),
            $stats['created'],
            implode(', ', self::DEMO_GRADES),
        ));
    }

    /**
     * Give the first class of each demo grade a real starting point: the earlier
     * skills mastered, a completed run on the last of them, and the next skill set
     * as this week's homework — so that class's pupils show progress and a lesson.
     */
    private function seedProgress(School $school): void
    {
        $year = Schoolyear::current() ?? Schoolyear::orderByDesc('id')->first();
        if (! $year) {
            return;
        }

        $grades = Grade::where('school_id', $school->id)->get()->keyBy('name');

        foreach (self::DEMO_GRADES as $gradeName) {
            $grade = $grades[$gradeName] ?? null;
            $offering = $grade
                ? Offering::where('schoolyear_id', $year->id)->where('grade_id', $grade->id)->first()
                : null;
            if (! $offering) {
                continue;
            }

            // The grade's skills, in teaching order.
            $skills = Skill::where('school_id', $school->id)
                ->where('grade_id', $grade->id)
                ->orderBy('level')->orderBy('scheduled_week')->orderBy('id')
                ->get()
                ->values();
            if ($skills->count() < 2) {
                continue;
            }

            // Mastered up to a third of the way in; the next skill is this week's homework.
            // A third of the way in is the class pace; the next skill is this week's homework.
            $masteredCount = max(1, (int) floor($skills->count() / 3));
            $homework = $skills[$masteredCount];

            $students = Enrollment::where('offering_id', $offering->id)->with('student')->get()
                ->map->student->filter()->values();

            // Give the whole class a believable spread, not just the first few pupils:
            // a couple haven't started, most sit around the class pace, a few are ahead.
            // Deterministic off the pupil id so a reset reproduces the same class shape.
            // This is pure DB (free); demo:reset only turns a few pupils per class into
            // real Kolibri completions (kolibri:demo-activity --limit).
            foreach ($students as $student) {
                $roll = ($student->id * 7 + 3) % 12;
                $personal = match (true) {
                    $roll <= 1 => 0,                                               // slow starter, nothing yet
                    $roll >= 10 => min($skills->count() - 1, $masteredCount + 2),  // ahead of the class
                    default => max(1, $masteredCount + ($roll % 3) - 1),           // around the class pace
                };
                if ($personal < 1) {
                    continue; // shows as 0 mastered — the class's slow starters
                }

                $mastered = $skills->take($personal);
                foreach ($mastered as $i => $skill) {
                    StudentSkill::firstOrCreate(
                        ['user_id' => $student->id, 'skill_id' => $skill->id],
                        ['status' => 'mastered', 'mastery' => min(99, 90 + $i), 'attempts' => 2, 'mastered_at' => Carbon::now()->subDays(20 - $i)],
                    );
                }

                $lastMastered = $mastered->last();
                $lastMap = $lastMastered->content()->wherePivot('role', 'practice')->first()
                    ?? $lastMastered->content()->first();
                ExerciseRun::firstOrCreate(
                    ['user_id' => $student->id, 'skill_id' => $lastMastered->id, 'status' => 'completed'],
                    [
                        'curriculum_map_id' => $lastMap?->id,
                        'mode' => 'free', 'started_at' => Carbon::now()->subHour(), 'completed_at' => Carbon::now()->subMinutes(48),
                        'total_questions' => 5, 'correct_answers' => 4, 'wrong_answers' => 1, 'score' => 80.00,
                    ],
                );
            }

            LessonAssignment::firstOrCreate(
                ['offering_id' => $offering->id, 'skill_id' => $homework->id, 'week_number' => SchoolCalendar::currentSchoolWeek()],
                ['assigned_by' => $offering->principal()->first()?->id ?? $students->first()?->id],
            );
        }
    }
}
