<?php

namespace App\Domain\Learning;

use App\Support\SchoolCalendar;
use Illuminate\Support\Collection;
use App\Domain\Learning\Models\ExerciseRun;
use App\Domain\Learning\Models\Skill;
use App\Domain\Learning\Models\StudentSkill;

/**
 * Traverses the skill prerequisite graph to find where a student
 * actually is, regardless of where their grade says they should be.
 *
 * Grade 6 says: "calculate area of rectangles"
 * Student can't → check prerequisite: "multiplication"
 * Student can't → check prerequisite: "repeated addition"
 * Student CAN → start here, build up.
 *
 * Pure pedagogy engine — no Kolibri dependency.
 */
class SkillGraph
{
    const MASTERED_REVIEW_WEEKS = [1, 3, 7, 15];
    const STRUGGLING_REVIEW_WEEKS = [2, 4, 8, 16];

    public function competenceMap(int $userId, int $subjectId): Collection
    {
        $skills = Skill::where('subject_id', $subjectId)
            ->orderBy('level')
            ->get();

        $studentSkills = StudentSkill::forStudent($userId)
            ->whereIn('skill_id', $skills->pluck('id'))
            ->get()
            ->keyBy('skill_id');

        return $skills->map(function (Skill $skill) use ($studentSkills) {
            $ss = $studentSkills->get($skill->id);
            return [
                'skill' => $skill,
                'status' => $ss ? $ss->status : 'not_started',
                'mastery' => $ss ? (float) $ss->mastery : 0,
                'attempts' => $ss ? $ss->attempts : 0,
            ];
        });
    }

    public function findStartingPoint(int $userId, Skill $targetSkill, array $visited = []): Skill
    {
        if (in_array($targetSkill->id, $visited, true)) {
            return $targetSkill;
        }

        $visited[] = $targetSkill->id;

        if ($this->hasMastered($userId, $targetSkill)) {
            return $targetSkill;
        }

        $prerequisites = $targetSkill->prerequisites()
            ->orderByDesc('level')
            ->get();

        foreach ($prerequisites as $prereq) {
            if ($this->hasMastered($userId, $prereq)) {
                continue;
            }

            return $this->findStartingPoint($userId, $prereq, $visited);
        }

        return $targetSkill;
    }

    public function learningPath(int $userId, Skill $targetSkill): Collection
    {
        $startingPoint = $this->findStartingPoint($userId, $targetSkill);

        return $this->pathBetween($startingPoint, $targetSkill)
            ->reject(fn (Skill $skill) => $this->hasMastered($userId, $skill));
    }

    public function readySkills(int $userId, int $subjectId, ?int $gradeId = null): Collection
    {
        $query = Skill::where('subject_id', $subjectId)
            ->with('prerequisites')
            ->orderBy('level');

        if ($gradeId) {
            $query->where('grade_id', $gradeId);
        }

        $skills = $query->get();

        $unlockedIds = StudentSkill::forStudent($userId)
            ->masteredOrStruggling()
            ->pluck('skill_id')
            ->toArray();

        return $skills->filter(function (Skill $skill) use ($unlockedIds) {
            if (in_array($skill->id, $unlockedIds, true)) {
                return false;
            }

            if ($skill->prerequisites->isEmpty()) {
                return true;
            }

            return $skill->prerequisites->every(
                fn (Skill $prereq) => in_array($prereq->id, $unlockedIds, true)
            );
        })->values();
    }

    /**
     * Record mastery of a skill based on exercise performance.
     *
     * Status transitions:
     * - in_progress → mastered (≥80% with 2+ runs)
     * - in_progress → struggling (5+ attempts, mastery <80%)
     * - struggling → mastered (≥80% on review)
     * - mastered/struggling → next review interval (≥80%) or reset interval (<80%)
     */
    public function recordAttempt(int $userId, Skill $skill, float $score, ?int $currentWeek = null): StudentSkill
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($userId, $skill, $score, $currentWeek) {
        $currentWeek ??= SchoolCalendar::currentSchoolWeek();

        $studentSkill = StudentSkill::firstOrCreate(
            ['user_id' => $userId, 'skill_id' => $skill->id],
            ['status' => 'not_started', 'mastery' => 0, 'attempts' => 0]
        );

        $studentSkill->last_attempted_at = now();
        $wasAlreadyMasteredOrStruggling = in_array($studentSkill->status, ['mastered', 'struggling'], true);

        $runs = ExerciseRun::forStudent($userId)
            ->forSkill($skill->id)
            ->completed()
            ->where('total_questions', '>', 0)
            ->latest('completed_at')
            ->take(5)
            ->get();

        if ($runs->isNotEmpty()) {
            $studentSkill->attempts = ExerciseRun::forStudent($userId)
                ->forSkill($skill->id)
                ->completed()
                ->count();

            $weights = [0.40, 0.25, 0.15, 0.12, 0.08];
            $totalWeight = 0;
            $weightedSum = 0;

            foreach ($runs as $i => $run) {
                $w = $weights[$i] ?? 0;
                $weightedSum += $run->score * $w;
                $totalWeight += $w;
            }

            $studentSkill->mastery = round($weightedSum / $totalWeight, 2);
        } else {
            $studentSkill->attempts++;
            $studentSkill->mastery = round(
                ($studentSkill->mastery * 0.3) + ($score * 0.7),
                2
            );
        }

        if ($wasAlreadyMasteredOrStruggling) {
            $schedule = $studentSkill->status === 'mastered'
                ? self::MASTERED_REVIEW_WEEKS
                : self::STRUGGLING_REVIEW_WEEKS;

            if ($score >= 80) {
                if ($studentSkill->status === 'struggling') {
                    $studentSkill->status = 'mastered';
                    $studentSkill->mastered_at = now();
                    $schedule = self::MASTERED_REVIEW_WEEKS;
                }

                $nextIdx = $studentSkill->review_interval_index + 1;
                if ($nextIdx < count($schedule)) {
                    $studentSkill->review_interval_index = $nextIdx;
                    $studentSkill->next_review_week = $currentWeek + $schedule[$nextIdx];
                } else {
                    $studentSkill->next_review_week = null;
                }
            } else {
                $studentSkill->review_interval_index = 0;
                $studentSkill->next_review_week = $currentWeek + $schedule[0];
            }
        } else {
            if ($studentSkill->mastery >= 80 && $studentSkill->attempts >= 2) {
                $studentSkill->status = 'mastered';
                $studentSkill->mastered_at = now();
                $studentSkill->next_review_week = $currentWeek + self::MASTERED_REVIEW_WEEKS[0];
                $studentSkill->review_interval_index = 0;
            } elseif ($studentSkill->attempts >= 5 && $studentSkill->mastery < 80) {
                $studentSkill->status = 'struggling';
                $studentSkill->next_review_week = $currentWeek + self::STRUGGLING_REVIEW_WEEKS[0];
                $studentSkill->review_interval_index = 0;
            } elseif ($studentSkill->attempts > 0) {
                $studentSkill->status = 'in_progress';
            }
        }

        $studentSkill->save();

        return $studentSkill;
        }); // end DB::transaction
    }

    public function diagnose(int $userId, int $subjectId, ?int $gradeId = null): array
    {
        $query = Skill::where('subject_id', $subjectId)->orderBy('level');

        if ($gradeId) {
            $query->where('grade_id', $gradeId);
        }

        $skills = $query->get();

        $masteredIds = StudentSkill::forStudent($userId)
            ->mastered()
            ->pluck('skill_id')
            ->toArray();

        $unlockedIds = StudentSkill::forStudent($userId)
            ->masteredOrStruggling()
            ->pluck('skill_id')
            ->toArray();

        $mastered = $skills->filter(fn ($s) => in_array($s->id, $masteredIds, true));
        $frontier = $this->readySkills($userId, $subjectId, $gradeId);
        $ahead = $skills->reject(fn ($s) => in_array($s->id, $unlockedIds, true))
            ->reject(fn ($s) => $frontier->contains('id', $s->id));

        return [
            'mastered' => $mastered->values(),
            'frontier' => $frontier,
            'ahead' => $ahead->values(),
            'mastery_percentage' => $skills->count() > 0
                ? round($mastered->count() / $skills->count() * 100)
                : 0,
        ];
    }

    public function recommendationsForStudent(
        int $userId,
        int $subjectId,
        ?int $gradeId,
        ?int $offeringId,
        int $currentWeek
    ): array {
        // Single query for all student skill records — avoids 5+ separate queries
        $allStudentSkills = StudentSkill::forStudent($userId)->get();
        $masteredIds = $allStudentSkills->where('status', 'mastered')->pluck('skill_id')->toArray();
        $strugglingIds = $allStudentSkills->where('status', 'struggling')->pluck('skill_id')->toArray();
        $inProgressIds = $allStudentSkills->where('status', 'in_progress')->pluck('skill_id')->toArray();
        $unlockedIds = array_merge($masteredIds, $strugglingIds);

        $diagnosis = $this->diagnose($userId, $subjectId, $gradeId);
        $frontier = $diagnosis['frontier'];

        $assignedSkill = null;
        if ($offeringId) {
            $assignedSkill = $this->assignedSkillForStudent($userId, $offeringId, $currentWeek);
        }

        $reviewSkill = $this->reviewDueSkill($userId, $currentWeek);

        $nextSkill = $frontier->first(function ($skill) use ($currentWeek) {
            return $skill->practiceContent()->exists()
                && ($skill->scheduled_week === null || $skill->scheduled_week <= $currentWeek);
        });

        if (!$nextSkill && !$assignedSkill && $gradeId) {
            $nextSkill = Skill::where('subject_id', $subjectId)
                ->where('grade_id', $gradeId)
                ->whereNotIn('id', $unlockedIds)
                ->whereHas('content', fn ($q) => $q->where('role', 'practice'))
                ->orderBy('level')
                ->first();
        }

        $allSkills = $gradeId
            ? Skill::where('subject_id', $subjectId)
                ->where('grade_id', $gradeId)
                ->whereHas('content', fn ($q) => $q->where('role', 'practice'))
                ->orderBy('level')
                ->get()
            : collect();

        $previousGradeSkills = $gradeId
            ? Skill::where('subject_id', $subjectId)
                ->where('grade_id', '<', $gradeId)
                ->whereHas('content', fn ($q) => $q->where('role', 'practice'))
                ->with('grade')
                ->orderByDesc('grade_id')
                ->orderBy('level')
                ->get()
                ->groupBy('grade_id')
            : collect();

        return [
            'diagnosis' => $diagnosis,
            'frontier' => $frontier,
            'nextSkill' => $nextSkill,
            'assignedSkill' => $assignedSkill,
            'reviewSkill' => $reviewSkill,
            'currentWeek' => $currentWeek,
            'allSkills' => $allSkills,
            'previousGradeSkills' => $previousGradeSkills,
            'masteredIds' => $masteredIds,
            'strugglingIds' => $strugglingIds,
            'inProgressIds' => $inProgressIds,
        ];
    }

    public function assignedSkillForStudent(int $userId, int $offeringId, int $week): ?Skill
    {
        $assignments = \App\Domain\Learning\Models\LessonAssignment::forOffering($offeringId)
            ->forWeek($week)
            ->with('skill')
            ->get();

        if ($assignments->isEmpty()) {
            return null;
        }

        $unlockedIds = StudentSkill::forStudent($userId)
            ->masteredOrStruggling()
            ->pluck('skill_id')
            ->toArray();

        foreach ($assignments as $assignment) {
            if (!$assignment->isForStudent($userId)) {
                continue;
            }
            if (!in_array($assignment->skill_id, $unlockedIds, true)
                && $assignment->skill->practiceContent()->exists()) {
                return $assignment->skill;
            }
        }

        return null;
    }

    public function reviewDueSkill(int $userId, int $currentWeek): ?Skill
    {
        $studentSkill = StudentSkill::forStudent($userId)
            ->reviewDue($currentWeek)
            ->orderByRaw("CASE WHEN status = 'struggling' THEN 0 ELSE 1 END")
            ->orderBy('next_review_week')
            ->first();

        if (!$studentSkill) {
            return null;
        }

        $skill = Skill::find($studentSkill->skill_id);

        if ($skill && $skill->practiceContent()->exists()) {
            return $skill;
        }

        return null;
    }

    private function hasMastered(int $userId, Skill $skill): bool
    {
        return StudentSkill::where('user_id', $userId)
            ->where('skill_id', $skill->id)
            ->whereIn('status', ['mastered', 'struggling'])
            ->exists();
    }

    private function pathBetween(Skill $from, Skill $to): Collection
    {
        if ($from->id === $to->id) {
            return collect([$from]);
        }

        $visited = [$from->id];
        $queue = [[$from]];

        while (!empty($queue)) {
            $path = array_shift($queue);
            $current = end($path);

            foreach ($current->dependents as $dependent) {
                if (in_array($dependent->id, $visited, true)) {
                    continue;
                }

                $newPath = array_merge($path, [$dependent]);

                if ($dependent->id === $to->id) {
                    return collect($newPath);
                }

                $visited[] = $dependent->id;
                $queue[] = $newPath;
            }
        }

        return collect([$from, $to]);
    }
}
