<?php

namespace App\Domain\Reporting\Repositories;

use App\Models\Enrollment;
use App\Models\School;
use App\Models\Term;

class NewTermReportRepository
{
    private $enrollment;
    private $offering;
    private $term;
    private $student;
    private $school;
    private $assessmentRepositories = [];

    public function __construct(Enrollment $enrollment, Term $term, School $school)
    {
        $this->enrollment = $enrollment->load('offering.principal');
        $this->offering = $this->enrollment->offering;
        $this->term = $term;
        $this->student = $this->enrollment->student;
        $this->school = $school;

        foreach ($school->assessmentTypes as $assessmentType) {
            // Only weighted types (Test/Exam) count toward a term report. Zero-weight
            // types such as the National Assessment Test are not part of a term and
            // must never appear on it.
            if ($assessmentType->weight <= 0) {
                continue;
            }
            $this->assessmentRepositories[$assessmentType->id] = new AssessmentRepository($assessmentType);
        }
    }

    public function getReportData()
    {
        return collect([
            'student' => $this->student,
            'schoolyear' => $this->enrollment->offering->schoolyear,
            'term' => $this->term,
            'grade' => $this->enrollment->offering->grade,
            'teacher' => $this->enrollment->offering->principal->first(),
            'results' => $this->getTermResults(),
        ]);
    }

    private function getTermResults()
    {
        $subjects = $this->offering->subjects($this->term)->get();

        $subjectResults = [];
        foreach ($subjects as $subject) {
            $subjectResults[$subject->name] = $this->getSubjectResults($subject);
        }

        // Subjects flagged "does not count toward total" still appear on the report,
        // but are excluded from the Total/Average — the figure used to rank pupils.
        // The flag resolves per class + term: a subject_term_offering pivot override
        // wins over the subject's school-wide default (see Subject::countsTowardTotalResolved).
        $countingNames = $subjects->filter(
            fn ($s) => $s->countsTowardTotalResolved()
        )->pluck('name')->all();
        $countingResults = collect($subjectResults)->only($countingNames);

        $reportTotal = $countingResults->reduce(function ($carry, $result) {
            return $carry + ($result['subjectTotal'] ?? 0);
        }, 0);

        return collect([
            'subjectResults' => $subjectResults,
            'total' => $reportTotal,
            'average' => round($this->calculateTermAverage($countingResults)),
        ]);
    }

    private function calculateTermAverage($subjectResults)
    {
        $subjectResultsWithScores = collect($subjectResults)->filter(function ($subjectResult) {
            return $subjectResult['hasScores'] === true;
        });

        $nrOfScores = $subjectResultsWithScores->count();

        $total = $subjectResultsWithScores->reduce(function ($carry, $item) {
            return $carry + $item['subjectTotal'];
        }, 0);

        if ($nrOfScores == 0) {
            return 0;
        }

        return $total / $nrOfScores;
    }

    private function getSubjectResults($subject)
    {
        $typeResults = [];
        $weightedScores = [];

        foreach ($this->assessmentRepositories as $typeId => $repository) {
            $result = $repository->getTermResults(
                $this->offering->id,
                $this->term->id,
                $subject->id,
                $this->student->id
            );
            $typeResults[$typeId] = $result;
            $weightedScores[] = $result['weightedScore'];
        }

        $subjectTotal = $this->getSubjectTotal($weightedScores);

        return collect([
            'typeResults' => $typeResults,
            'subjectTotal' => $subjectTotal,
            'hasScores' => $subjectTotal !== null,
        ]);
    }

    private function getSubjectTotal(array $weightedScores)
    {
        foreach ($weightedScores as $score) {
            if (is_null($score)) {
                return null;
            }
        }

        return array_sum($weightedScores);
    }
}
