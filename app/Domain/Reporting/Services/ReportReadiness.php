<?php

namespace App\Domain\Reporting\Services;

use App\Models\Assessment;
use App\Models\Offering;
use App\Models\School;
use App\Models\Term;
use Illuminate\Support\Collection;

/**
 * Flags subjects that will silently drop off the term report: a subject's total
 * only computes when every weighted type (Test AND Exam) has a score, so a
 * subject with marks in one column but not the other has no total and vanishes
 * from the card. That is easy to miss, e.g. an exam saved under the Test type.
 * This surfaces those subjects before staff print.
 */
class ReportReadiness
{
    /**
     * Subjects with marks in some — but not all — weighted types for this class/term.
     *
     * @return Collection<int, array{subject:string, has:array<int,string>, missing:array<int,string>}>
     */
    public function incompleteSubjects(Offering $offering, Term $term, School $school): Collection
    {
        $weighted = $school->assessmentTypes->where('weight', '>', 0)->values();
        if ($weighted->count() < 2) {
            return collect();
        }

        return $offering->subjects($term->id)->get()
            ->map(function ($subject) use ($offering, $term, $weighted) {
                $present = $weighted->mapWithKeys(fn ($type) => [
                    $type->name => Assessment::where('offering_id', $offering->id)
                        ->where('subject_id', $subject->id)
                        ->where('term_id', $term->id)
                        ->where('assessment_type_id', $type->id)
                        ->whereHas('scores')
                        ->exists(),
                ]);

                // Only a subject that has started (some marks) but is missing a type.
                if (! $present->contains(true) || ! $present->contains(false)) {
                    return null;
                }

                return [
                    'subject' => $subject->name,
                    'has' => $present->filter()->keys()->all(),
                    'missing' => $present->reject(fn ($v) => $v)->keys()->all(),
                ];
            })
            ->filter()
            ->values();
    }
}
