<?php

namespace Tests\Feature;

use App\Domain\Reporting\Repositories\NewTermReportRepository;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AssessmentType;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\School;
use App\Models\Subject;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class AssessmentReportTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private AssessmentType $testType;
    private AssessmentType $examType;

    public function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create();
        $this->testType = AssessmentType::factory()->test()->create(['school_id' => $this->school->id]);
        $this->examType = AssessmentType::factory()->exam()->create(['school_id' => $this->school->id]);
    }

    #[Test]
    public function it_can_generate_a_report_collection()
    {
        $enrollment = $this->createEnrollment();

        $report = new NewTermReportRepository($enrollment, $this->term, $this->school);

        $this->assertInstanceOf('Illuminate\Support\Collection', $report->getReportData());
    }

    #[Test]
    public function it_contains_assessments()
    {
        $enrollment = $this->createEnrollment();
        $subject = $this->createSubjectForOffering($enrollment->offering);

        $assessment = $this->createAssessment($enrollment, $subject, $this->testType, maxScore: 10);
        $this->createScore($enrollment, $assessment, score: 8);

        $report = new NewTermReportRepository($enrollment, $this->term, $this->school);
        $data = $report->getReportData();

        $typeResults = $data['results']['subjectResults'][$subject->name]['typeResults'][$this->testType->id];
        $this->assertTrue($typeResults['assessments']->contains($assessment));
    }

    #[Test]
    public function it_calculates_subject_total_from_weighted_test_and_exam_scores()
    {
        $enrollment = $this->createEnrollment();
        $subject = $this->createSubjectForOffering($enrollment->offering);

        // Create 2 tests: 8/10 (80%) and 6/10 (60%) → average 70%
        // Weighted at 25%: 70 * 0.25 = 17.5 → rounds to 18
        $test1 = $this->createAssessment($enrollment, $subject, $this->testType, maxScore: 10);
        $this->createScore($enrollment, $test1, score: 8);
        $test2 = $this->createAssessment($enrollment, $subject, $this->testType, maxScore: 10);
        $this->createScore($enrollment, $test2, score: 6);

        // Create 1 exam: 60/75 (80%)
        // Weighted at 75%: 80 * 0.75 = 60
        $exam = $this->createAssessment($enrollment, $subject, $this->examType, maxScore: 75);
        $this->createScore($enrollment, $exam, score: 60);

        // Expected total: 18 + 60 = 78
        $report = new NewTermReportRepository($enrollment, $this->term, $this->school);
        $data = $report->getReportData();

        $this->assertEquals(78, $data['results']['subjectResults'][$subject->name]['subjectTotal']);
    }

    #[Test]
    public function it_calculates_term_total_across_multiple_subjects()
    {
        $enrollment = $this->createEnrollment();

        // Subject 1: test 8/10 (80%) → 80 * 0.25 = 20, exam 60/75 (80%) → 80 * 0.75 = 60
        // Subject total: 20 + 60 = 80
        $subject1 = $this->createSubjectForOffering($enrollment->offering, 'Math');
        $test1 = $this->createAssessment($enrollment, $subject1, $this->testType, maxScore: 10);
        $this->createScore($enrollment, $test1, score: 8);
        $exam1 = $this->createAssessment($enrollment, $subject1, $this->examType, maxScore: 75);
        $this->createScore($enrollment, $exam1, score: 60);

        // Subject 2: test 10/10 (100%) → 100 * 0.25 = 25, exam 75/75 (100%) → 100 * 0.75 = 75
        // Subject total: 25 + 75 = 100
        $subject2 = $this->createSubjectForOffering($enrollment->offering, 'English');
        $test2 = $this->createAssessment($enrollment, $subject2, $this->testType, maxScore: 10);
        $this->createScore($enrollment, $test2, score: 10);
        $exam2 = $this->createAssessment($enrollment, $subject2, $this->examType, maxScore: 75);
        $this->createScore($enrollment, $exam2, score: 75);

        // Expected total: 80 + 100 = 180
        $report = new NewTermReportRepository($enrollment, $this->term, $this->school);
        $data = $report->getReportData();

        $this->assertEquals(180, $data['results']['total']);
    }

    #[Test]
    public function it_calculates_term_average_across_subjects()
    {
        $enrollment = $this->createEnrollment();

        // Subject 1: test 80%, exam 80% → total 80
        $subject1 = $this->createSubjectForOffering($enrollment->offering, 'Math');
        $test1 = $this->createAssessment($enrollment, $subject1, $this->testType, maxScore: 10);
        $this->createScore($enrollment, $test1, score: 8);
        $exam1 = $this->createAssessment($enrollment, $subject1, $this->examType, maxScore: 75);
        $this->createScore($enrollment, $exam1, score: 60);

        // Subject 2: test 100%, exam 100% → total 100
        $subject2 = $this->createSubjectForOffering($enrollment->offering, 'English');
        $test2 = $this->createAssessment($enrollment, $subject2, $this->testType, maxScore: 10);
        $this->createScore($enrollment, $test2, score: 10);
        $exam2 = $this->createAssessment($enrollment, $subject2, $this->examType, maxScore: 75);
        $this->createScore($enrollment, $exam2, score: 75);

        // Expected average: (80 + 100) / 2 = 90
        $report = new NewTermReportRepository($enrollment, $this->term, $this->school);
        $data = $report->getReportData();

        $this->assertEquals(90, $data['results']['average']);
    }

    #[Test]
    public function it_returns_null_subject_total_when_a_type_has_no_scores()
    {
        $enrollment = $this->createEnrollment();
        $subject = $this->createSubjectForOffering($enrollment->offering);

        // Only exam, no tests
        $exam = $this->createAssessment($enrollment, $subject, $this->examType, maxScore: 75);
        $this->createScore($enrollment, $exam, score: 60);

        $report = new NewTermReportRepository($enrollment, $this->term, $this->school);
        $data = $report->getReportData();

        $this->assertNull($data['results']['subjectResults'][$subject->name]['subjectTotal']);
    }

    #[Test]
    public function it_works_with_three_assessment_types()
    {
        // Override: CA1 20%, CA2 20%, Exam 60%
        $school = School::factory()->create();
        $ca1 = AssessmentType::factory()->create(['school_id' => $school->id, 'name' => 'CA1', 'weight' => 0.2000]);
        $ca2 = AssessmentType::factory()->create(['school_id' => $school->id, 'name' => 'CA2', 'weight' => 0.2000]);
        $examType = AssessmentType::factory()->create(['school_id' => $school->id, 'name' => 'Exam', 'weight' => 0.6000]);

        $enrollment = $this->createEnrollment();
        $subject = $this->createSubjectForOffering($enrollment->offering);

        // CA1: 8/10 (80%) → 80 * 0.20 = 16
        $a1 = $this->createAssessment($enrollment, $subject, $ca1, maxScore: 10);
        $this->createScore($enrollment, $a1, score: 8);

        // CA2: 7/10 (70%) → 70 * 0.20 = 14
        $a2 = $this->createAssessment($enrollment, $subject, $ca2, maxScore: 10);
        $this->createScore($enrollment, $a2, score: 7);

        // Exam: 60/100 (60%) → 60 * 0.60 = 36
        $a3 = $this->createAssessment($enrollment, $subject, $examType, maxScore: 100);
        $this->createScore($enrollment, $a3, score: 60);

        // Expected total: 16 + 14 + 36 = 66
        $report = new NewTermReportRepository($enrollment, $this->term, $school);
        $data = $report->getReportData();

        $this->assertEquals(66, $data['results']['subjectResults'][$subject->name]['subjectTotal']);
    }

    #[Test]
    public function it_counts_absent_scores_as_zero()
    {
        // Despite the name "absent", an absent score is treated as
        // "absent" — counted as 0% in the average. School has not yet
        // confirmed whether they want true exclusion for future terms.
        $enrollment = $this->createEnrollment();
        $subject = $this->createSubjectForOffering($enrollment->offering);

        // Test 1: 8/10 (80%), Test 2: absent → counted as 0%
        // Test average: (80 + 0) / 2 = 40% → weighted 40 * 0.25 = 10
        $test1 = $this->createAssessment($enrollment, $subject, $this->testType, maxScore: 10);
        $this->createScore($enrollment, $test1, score: 8);
        $test2 = $this->createAssessment($enrollment, $subject, $this->testType, maxScore: 10);
        AssessmentScore::factory()->create([
            'user_id' => $enrollment->user_id,
            'assessment_id' => $test2->id,
            'score' => null,
            'absent' => true,
        ]);

        // Exam: 60/75 (80%) → 80 * 0.75 = 60
        $exam = $this->createAssessment($enrollment, $subject, $this->examType, maxScore: 75);
        $this->createScore($enrollment, $exam, score: 60);

        // Expected: 10 + 60 = 70
        $report = new NewTermReportRepository($enrollment, $this->term, $this->school);
        $data = $report->getReportData();

        $this->assertEquals(70, $data['results']['subjectResults'][$subject->name]['subjectTotal']);
    }

    #[Test]
    public function it_excludes_the_national_assessment_test_from_a_term_report()
    {
        // A NAT is a national exam, not part of a term: weight 0, no term_id.
        $natType = AssessmentType::factory()->create([
            'school_id' => $this->school->id,
            'name' => 'National Assessment Test',
            'weight' => 0,
        ]);

        $enrollment = $this->createEnrollment();
        $subject = $this->createSubjectForOffering($enrollment->offering);

        // Real term assessments: Test 8/10 (80% -> 80*0.25 = 20) + Exam 60/75 (80% -> 80*0.75 = 60) = 80.
        $test = $this->createAssessment($enrollment, $subject, $this->testType, maxScore: 10);
        $this->createScore($enrollment, $test, score: 8);
        $exam = $this->createAssessment($enrollment, $subject, $this->examType, maxScore: 75);
        $this->createScore($enrollment, $exam, score: 60);

        // A NAT assessment for the same offering/subject, term-less, with a high score.
        $nat = Assessment::factory()->create([
            'assessment_type_id' => $natType->id,
            'offering_id' => $enrollment->offering_id,
            'term_id' => null,
            'subject_id' => $subject->id,
            'max_score' => 100,
        ]);
        $this->createScore($enrollment, $nat, score: 95);

        $report = new NewTermReportRepository($enrollment, $this->term, $this->school);
        $data = $report->getReportData();

        $typeResults = $data['results']['subjectResults'][$subject->name]['typeResults'];

        // the NAT type must not appear among the term-report result types...
        $this->assertArrayNotHasKey($natType->id, $typeResults);
        // ...and the subject total reflects only the weighted Test + Exam (80), not the NAT 95.
        $this->assertEquals(80, $data['results']['subjectResults'][$subject->name]['subjectTotal']);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    private function createEnrollment(): Enrollment
    {
        $offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        return Enrollment::factory()->create([
            'offering_id' => $offering->id,
        ]);
    }

    private function createSubjectForOffering(Offering $offering, ?string $name = null): Subject
    {
        $subject = Subject::factory()->create([
            'name' => $name ?? 'Subject ' . uniqid(),
        ]);
        $offering->subjects()->save($subject, ['term_id' => $this->term->id]);

        return $subject;
    }

    private function createAssessment(Enrollment $enrollment, Subject $subject, AssessmentType $type, int $maxScore): Assessment
    {
        return Assessment::factory()->create([
            'assessment_type_id' => $type->id,
            'offering_id' => $enrollment->offering_id,
            'term_id' => $this->term->id,
            'subject_id' => $subject->id,
            'max_score' => $maxScore,
        ]);
    }

    private function createScore(Enrollment $enrollment, Assessment $assessment, int $score): AssessmentScore
    {
        return AssessmentScore::factory()->create([
            'user_id' => $enrollment->user_id,
            'assessment_id' => $assessment->id,
            'score' => $score,
        ]);
    }
}
