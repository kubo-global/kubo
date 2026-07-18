<?php

namespace Tests\Feature;

use App\Domain\Reporting\Repositories\NewTermReportRepository;
use App\Models\AssessmentType;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\Term;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\CreatesApplication;

/**
 * Regression test: verifies the NEW assessment-based report system
 * produces identical numbers to the OLD test/exam system.
 *
 * Requires: kubo_production_backup database with migrated assessment data.
 * Requires: tests/fixtures/golden_master.json from the old system.
 */
class AssessmentReportRegressionTest extends BaseTestCase
{
    use CreatesApplication;

    private array $goldenMaster = [];
    private ?School $school = null;
    private ?int $testTypeId = null;
    private ?int $examTypeId = null;

    public function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql.database', 'kubo_production_backup');
        \DB::purge('mysql');
        \DB::reconnect('mysql');

        $goldenPath = base_path('tests/fixtures/golden_master.json');
        if (!file_exists($goldenPath)) {
            $this->markTestSkipped('Golden master not generated. Run: php artisan snapshot:reports');
        }

        $this->goldenMaster = json_decode(file_get_contents($goldenPath), true);
        if (empty($this->goldenMaster)) {
            $this->markTestSkipped('Golden master is empty.');
        }

        $this->school = School::first();
        if (!$this->school || $this->school->assessmentTypes()->count() === 0) {
            $this->markTestSkipped('No school or assessment types. Run the migration first.');
        }

        $this->testTypeId = AssessmentType::where('school_id', $this->school->id)
            ->whereRaw('LOWER(name) = ?', ['test'])->value('id');
        $this->examTypeId = AssessmentType::where('school_id', $this->school->id)
            ->whereRaw('LOWER(name) = ?', ['exam'])->value('id');
    }

    #[Test]
    public function test_all_reports_match_golden_master()
    {
        $diffs = [];
        $tested = 0;

        foreach ($this->goldenMaster as $key => $expected) {
            $enrollment = Enrollment::find($expected['enrollment_id']);
            $term = Term::find($expected['term_id']);

            if (!$enrollment || !$term) {
                continue;
            }

            try {
                $termReport = new NewTermReportRepository($enrollment, $term, $this->school);
                $data = $termReport->getReportData();
                $results = $data['results'];

                // Compare report-level totals
                $actualTotal = $results['total'] ?? null;
                $actualAverage = $results['average'] ?? null;

                if ($actualTotal != $expected['total']) {
                    $diffs[] = "{$key}: total expected={$expected['total']} actual={$actualTotal}";
                }
                if ($actualAverage != $expected['average']) {
                    $diffs[] = "{$key}: average expected={$expected['average']} actual={$actualAverage}";
                }

                // Compare per-subject values
                foreach ($expected['subjects'] as $subjectName => $expectedSubject) {
                    $actualSubject = $results['subjectResults'][$subjectName] ?? null;

                    if (!$actualSubject) {
                        $diffs[] = "{$key}: subject '{$subjectName}' missing from new report";
                        continue;
                    }

                    $actualTestWS = $actualSubject['typeResults'][$this->testTypeId]['weightedScore'] ?? null;
                    $actualExamWS = $actualSubject['typeResults'][$this->examTypeId]['weightedScore'] ?? null;
                    $actualSubjectTotal = $actualSubject['subjectTotal'] ?? null;

                    if ($actualTestWS != $expectedSubject['testWeightedScore']) {
                        $diffs[] = "{$key}/{$subjectName}: testWeightedScore expected={$expectedSubject['testWeightedScore']} actual={$actualTestWS}";
                    }
                    if ($actualExamWS != $expectedSubject['examWeightedScore']) {
                        $diffs[] = "{$key}/{$subjectName}: examWeightedScore expected={$expectedSubject['examWeightedScore']} actual={$actualExamWS}";
                    }
                    if ($actualSubjectTotal != $expectedSubject['subjectTotal']) {
                        $diffs[] = "{$key}/{$subjectName}: subjectTotal expected={$expectedSubject['subjectTotal']} actual={$actualSubjectTotal}";
                    }
                }

                $tested++;
            } catch (\Throwable $e) {
                $diffs[] = "{$key}: ERROR " . $e->getMessage();
            }
        }

        $this->assertGreaterThan(0, $tested, 'No reports were tested');

        if (!empty($diffs)) {
            $sample = array_slice($diffs, 0, 20);
            $this->fail(
                count($diffs) . " differences found (showing first 20):\n" . implode("\n", $sample)
            );
        }
    }

    #[Test]
    public function test_sample_report_structure_matches()
    {
        $firstKey = array_key_first($this->goldenMaster);
        $expected = $this->goldenMaster[$firstKey];

        $enrollment = Enrollment::find($expected['enrollment_id']);
        $term = Term::find($expected['term_id']);

        $termReport = new NewTermReportRepository($enrollment, $term, $this->school);
        $data = $termReport->getReportData();

        // Check the data structure matches old format
        $this->assertNotNull($data['student']);
        $this->assertNotNull($data['term']);
        $this->assertNotNull($data['grade']);
        $this->assertArrayHasKey('subjectResults', $data['results']->toArray());
        $this->assertArrayHasKey('total', $data['results']->toArray());
        $this->assertArrayHasKey('average', $data['results']->toArray());

        $firstSubject = collect($data['results']['subjectResults'])->first();
        if ($firstSubject) {
            $this->assertArrayHasKey('typeResults', $firstSubject->toArray());
            $this->assertArrayHasKey('subjectTotal', $firstSubject->toArray());
            $this->assertArrayHasKey('hasScores', $firstSubject->toArray());
        }
    }
}
