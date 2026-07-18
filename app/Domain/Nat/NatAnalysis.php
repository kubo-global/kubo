<?php

namespace App\Domain\Nat;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Offering;
use App\Models\School;
use Illuminate\Support\Collection;

/**
 * Computes the National Assessment Test (NAT) analysis for one sitting
 * (an Offering = a grade in a school year), reusing the existing
 * Assessment / AssessmentScore data.
 *
 * Per-subject marks are out of 100. A student "passes" at >= failBelow and
 * reaches "mastery" at >= masteryAt. Pass is cumulative (includes mastery).
 * Absent students are stored as absent AssessmentScores (blank score) and
 * excluded from every denominator (the WAEC "A").
 *
 * Gambia defaults (fail < 40, mastery >= 80) are constructor arguments so a
 * different country can pass its own thresholds without code changes.
 */
class NatAnalysis
{
    public const FAIL_BELOW = 40;
    public const MASTERY_AT = 80;

    public function __construct(
        private Offering $offering,
        private AssessmentType $natType,
        private int $failBelow = self::FAIL_BELOW,
        private int $masteryAt = self::MASTERY_AT,
    ) {
    }

    /**
     * Resolve [failBelow, masteryAt] from the school's NAT GradingScale bands
     * (purpose = 'nat', labels "Pass" and "Mastery"), falling back to the
     * Gambia defaults when a school has not configured its own bands.
     */
    public static function thresholdsForSchool(?School $school): array
    {
        $fail = self::FAIL_BELOW;
        $mastery = self::MASTERY_AT;

        if ($school) {
            $bands = $school->gradingScales()
                ->where('purpose', 'nat')
                ->get()
                ->keyBy(fn ($b) => strtolower($b->label));

            if ($pass = $bands->get('pass')) {
                $fail = (int) round($pass->min_score);
            }
            if ($mastered = $bands->get('mastery')) {
                $mastery = (int) round($mastered->min_score);
            }
        }

        return [$fail, $mastery];
    }

    /**
     * One Assessment per subject for this sitting, in creation order (the order
     * the subjects were entered / seeded), which matches the WAEC result sheet.
     * NOT alphabetical: that would reorder e.g. Science/S.E.S. and Maths/Int. Std.
     */
    public function assessments(): Collection
    {
        return Assessment::query()
            ->where('offering_id', $this->offering->id)
            ->where('assessment_type_id', $this->natType->id)
            ->with(['subject', 'scores'])
            ->orderBy('id')
            ->get();
    }

    /** user_id => 'm' | 'f' | '' for every enrolled student. */
    private function genderByUser(): Collection
    {
        return $this->offering->enrollments()
            ->with('student.profile')
            ->get()
            ->mapWithKeys(fn ($e) => [
                $e->user_id => strtolower($e->student?->profile?->gender ?? ''),
            ]);
    }

    /**
     * Student-by-subject score matrix for the scores listing.
     * Each row: ['student', 'gender', 'scores' => [assessmentId => int|null], 'total'].
     * A null score means absent (printed as "A").
     */
    public function studentRows(): Collection
    {
        $assessments = $this->assessments();
        $gender = $this->genderByUser();

        $lookup = [];
        foreach ($assessments as $a) {
            foreach ($a->scores as $sc) {
                $lookup[$a->id][$sc->user_id] = $sc;
            }
        }

        // only students who actually have a NAT record (sat or marked absent); an
        // enrolled student who never sat the NAT is left off the listing entirely.
        $scoredUserIds = collect($lookup)->flatMap(fn ($byUser) => array_keys($byUser))->unique()->flip();

        $students = $this->offering->enrollments()
            ->with('student.profile')
            ->get()
            ->map(fn ($e) => $e->student)
            ->filter()
            ->filter(fn ($s) => $scoredUserIds->has($s->id))
            ->sortBy(fn ($s) => $s->last_name.' '.$s->first_name)
            ->values();

        return $students->map(function ($student) use ($assessments, $lookup, $gender) {
            $scores = [];
            $total = 0;
            foreach ($assessments as $a) {
                $sc = $lookup[$a->id][$student->id] ?? null;
                if ($sc && ! $sc->absent && $sc->score !== null) {
                    $scores[$a->id] = (int) $sc->score;
                    $total += (int) $sc->score;
                } else {
                    $scores[$a->id] = null;
                }
            }

            return [
                'student' => $student,
                'gender' => $gender[$student->id] ?? '',
                'scores' => $scores,
                'total' => $total,
            ];
        });
    }

    /**
     * Per-subject Male / Female / All breakdown.
     * Each entry: ['subject', 'max_score', 'groups' => Collection<label => stats>].
     */
    public function breakdowns(): Collection
    {
        $gender = $this->genderByUser();

        return $this->assessments()->map(function (Assessment $a) use ($gender) {
            $present = collect($a->scores)
                ->filter(fn ($sc) => ! $sc->absent && $sc->score !== null)
                ->map(fn ($sc) => [
                    'score' => (int) $sc->score,
                    'gender' => $gender[$sc->user_id] ?? '',
                ]);

            $groups = collect([
                'Male' => $present->where('gender', 'm'),
                'Female' => $present->where('gender', 'f'),
                'All' => $present,
            ])->map(fn ($g, $label) => $this->stats($label, $g->pluck('score')));

            return [
                'subject' => $a->subject,
                'max_score' => $a->max_score,
                'groups' => $groups,
            ];
        });
    }

    private function stats(string $label, Collection $scores): array
    {
        $n = $scores->count();
        $fail = $scores->filter(fn ($s) => $s < $this->failBelow)->count();
        $pass = $scores->filter(fn ($s) => $s >= $this->failBelow)->count();
        $mastery = $scores->filter(fn ($s) => $s >= $this->masteryAt)->count();
        $pct = fn (int $x) => $n > 0 ? $x / $n : 0.0;

        return [
            'label' => $label,
            'n' => $n,
            'fail' => $fail,
            'pct_fail' => $pct($fail),
            'pass' => $pass,
            'pct_pass' => $pct($pass),
            'mastery' => $mastery,
            'pct_mastery' => $pct($mastery),
            'average' => $n > 0 ? round($scores->avg()) : null,
        ];
    }
}
