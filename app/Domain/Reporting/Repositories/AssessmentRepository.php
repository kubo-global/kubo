<?php

namespace App\Domain\Reporting\Repositories;

use App\Models\Assessment;
use App\Models\AssessmentType;

class AssessmentRepository
{
    private AssessmentType $assessmentType;

    public function __construct(AssessmentType $assessmentType)
    {
        $this->assessmentType = $assessmentType;
    }

    public function getTermResults($offeringId, $termId, $subjectId, $studentId)
    {
        $assessmentsWithScores = $this->getAssessmentsWithScores($offeringId, $termId, $subjectId, $studentId);

        $average = $this->calculateAverageFor($assessmentsWithScores);

        // A "real mark": a score that was actually entered (not absent, not blank).
        // Read off the loaded model so it is null-safe on legacy schemas that
        // predate the `absent` column (there, any score row is a real mark).
        $hasRealMark = $assessmentsWithScores->contains(function ($a) {
            $s = $a->scores->first();

            return $s && $s['score'] !== null && ! ($s->absent ?? false);
        });

        return [
            'assessments' => $assessmentsWithScores,
            // Match old behavior: return 0 instead of null when no scores exist
            'average' => round($average ?? 0),
            'weightedScore' => $this->calculateWeightedScore($average),
            'hasRealMark' => $hasRealMark,
        ];
    }

    public function getAssessmentsWithScores($offeringId, $termId, $subjectId, $studentId)
    {
        return Assessment::where('assessment_type_id', $this->assessmentType->id)
            ->where('offering_id', $offeringId)
            ->where('subject_id', $subjectId)
            ->where('term_id', $termId)
            ->with([
                'scores' => function ($q) use ($studentId) {
                    $q->where('assessment_scores.user_id', $studentId);
                }
            ])->get();
    }

    private function calculateAverageFor($assessments)
    {
        // An absent score (absent=true, score=null) counts as 0% in the average
        // — PHP coerces null to 0 in the division below. This is intended: the
        // school reconciles subjects against calculated totals, so absences must
        // count as 0 rather than be skipped from the divisor.
        $assessmentsWithScores = $assessments->filter(function ($item) {
            return !$item->scores->isEmpty();
        });

        $scoresAndMaxScores = $assessmentsWithScores->map(function ($assessment) {
            return [
                'score' => $assessment->scores->first()['score'],
                'maxScore' => $assessment['max_score'],
            ];
        });

        if ($scoresAndMaxScores->count() === 0) {
            return null;
        }

        $total = $scoresAndMaxScores->reduce(function ($carry, $item) {
            return $carry + ($item['score'] / $item['maxScore']);
        }, 0);

        return $total / $scoresAndMaxScores->count() * 100;
    }

    private function calculateWeightedScore($score)
    {
        if ($score === null) {
            return null;
        }

        return round($score * $this->assessmentType->weight);
    }
}
