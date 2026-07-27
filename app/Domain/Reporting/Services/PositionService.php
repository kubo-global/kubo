<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Reporting\Repositories\ClassTermReportRepository;
use App\Models\Offering;
use App\Models\School;
use App\Models\Term;
use Illuminate\Support\Collection;

/**
 * Ranks the pupils of a class for a term by their term Total (the same total the
 * report uses, so non-counting subjects are already excluded). Ties share a
 * position. Single source of truth for both the Positions view and the report
 * book's Position column.
 */
class PositionService
{
    /**
     * Full ranked reports for a class/term: each row carries the pupil's complete
     * term report data ('report'), total, average and position (ties shared).
     * The report book reuses 'report' for the subject cells, so the class is
     * computed once per term — not once per pupil.
     *
     * @return Collection<int,array{report:mixed,student:mixed,student_id:int,total:int|float,average:int|float,position:int}>
     */
    public function rankedReports(Offering $offering, Term $term, School $school): Collection
    {
        $enrollments = $offering->enrollments()
            ->join('users', 'enrollments.user_id', '=', 'users.id')
            ->where('users.archived', false)
            ->select('enrollments.*')->with('student')->get();

        $sorted = (new ClassTermReportRepository($enrollments, $term, $school))->getReports()
            ->filter(fn ($r) => $r['sat'] ?? true)   // a pupil who did not sit is not ranked or counted
            ->sortByDesc(fn ($r) => $r['results']['total'])->values();

        $position = 0; $rank = 0; $prev = null;
        return $sorted->map(function ($r) use (&$position, &$rank, &$prev) {
            $rank++;
            $total = $r['results']['total'];
            if ($total !== $prev) {
                $position = $rank;
                $prev = $total;
            }
            return [
                'report'     => $r,
                'student'    => $r['student'],
                'student_id' => $r['student']->id,
                'total'      => $total,
                'average'    => $r['results']['average'],
                'position'   => $position,
            ];
        });
    }

    /** @return Collection<int,array{student:mixed,student_id:int,total:int|float,average:int|float,position:int}> */
    public function rank(Offering $offering, Term $term, School $school): Collection
    {
        return $this->rankedReports($offering, $term, $school)->map(fn ($x) => [
            'student'    => $x['student'],
            'student_id' => $x['student_id'],
            'total'      => $x['total'],
            'average'    => $x['average'],
            'position'   => $x['position'],
        ]);
    }

    /** A single pupil's position in their class for a term, or null if not ranked. */
    public function positionFor(Offering $offering, Term $term, School $school, int $studentId): ?int
    {
        $row = $this->rank($offering, $term, $school)->firstWhere('student_id', $studentId);
        return $row['position'] ?? null;
    }
}
