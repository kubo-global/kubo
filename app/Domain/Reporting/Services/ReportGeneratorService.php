<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Reporting\Repositories\ClassTermReportRepository;
use App\Domain\Reporting\Repositories\NewTermReportRepository;
use PDF;

class ReportGeneratorService
{
    public function generateStudentReportPdf(NewTermReportRepository $termReport)
    {
        return array($termReport->getReportData());
    }

    public function generateClassReportPdf(ClassTermReportRepository $classTermReport)
    {
        return $classTermReport->getReports();
    }
}
