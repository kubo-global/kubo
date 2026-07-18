<?php

namespace App\Domain\Reporting\Repositories;

use App\Models\School;
use App\Models\Term;

class ClassTermReportRepository
{
    private $enrollments;
    private $term;
    private $school;

    public function __construct($enrollments, Term $term, School $school)
    {
        $this->enrollments = $enrollments;
        $this->term = $term;
        $this->school = $school;
    }

    public function getReports()
    {
        return collect($this->enrollments)->map(function ($item) {
            $termReport = new NewTermReportRepository($item, $this->term, $this->school);
            return $termReport->getReportData();
        });
    }
}
