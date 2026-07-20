<?php

namespace Tests\Feature;

use App\Domain\Reporting\Repositories\NewTermReportRepository;
use App\Domain\Reporting\Services\PositionService;
use App\Domain\Reporting\Services\ReportGeneratorService;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AssessmentType;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradingScale;
use App\Models\Offering;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Swallow term-card layout auto-fills the fields staff used to write by hand:
 * position, average, class size, and the per-subject grade remark from the rubric.
 */
class SwallowReportCardTest extends TestCase
{
    use RefreshDatabase;

    /** Grade 5 class, one counting subject with a Test + Exam entered for two pupils. */
    private function scoredClass(): array
    {
        $school = School::first() ?? School::factory()->create();
        $test = AssessmentType::factory()->test()->create(['school_id' => $school->id]); // weight 0.25
        $exam = AssessmentType::factory()->exam()->create(['school_id' => $school->id]); // weight 0.75

        $offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id' => Grade::factory()->create(['name' => 'Grade 5'])->id,
        ]);
        $maths = Subject::factory()->create(['name' => 'Mathematics', 'counts_toward_total' => true]);
        $offering->subjects($this->term->id)->save($maths, ['term_id' => $this->term->id]);

        $testA = Assessment::factory()->create(['assessment_type_id' => $test->id, 'offering_id' => $offering->id, 'term_id' => $this->term->id, 'subject_id' => $maths->id, 'max_score' => 100]);
        $examA = Assessment::factory()->create(['assessment_type_id' => $exam->id, 'offering_id' => $offering->id, 'term_id' => $this->term->id, 'subject_id' => $maths->id, 'max_score' => 100]);

        $top = Student::factory()->create(['first_name' => 'Top', 'last_name' => 'Pupil']);
        $low = Student::factory()->create(['first_name' => 'Low', 'last_name' => 'Pupil']);
        Enrollment::factory()->create(['user_id' => $top->id, 'offering_id' => $offering->id]);
        Enrollment::factory()->create(['user_id' => $low->id, 'offering_id' => $offering->id]);

        // Both weighted types entered, so the term total (and thus a grade) resolves.
        // Top: 0.25*80 + 0.75*80 = 80 → grade A. Low: 40.
        AssessmentScore::factory()->create(['user_id' => $top->id, 'assessment_id' => $testA->id, 'score' => 80]);
        AssessmentScore::factory()->create(['user_id' => $top->id, 'assessment_id' => $examA->id, 'score' => 80]);
        AssessmentScore::factory()->create(['user_id' => $low->id, 'assessment_id' => $testA->id, 'score' => 40]);
        AssessmentScore::factory()->create(['user_id' => $low->id, 'assessment_id' => $examA->id, 'score' => 40]);

        GradingScale::create(['school_id' => $school->id, 'purpose' => null, 'label' => 'A', 'remark' => 'Excellent', 'min_score' => 80, 'max_score' => 100]);
        GradingScale::create(['school_id' => $school->id, 'purpose' => null, 'label' => 'D', 'remark' => 'Fairly good', 'min_score' => 0, 'max_score' => 49]);

        $school->configs()->updateOrCreate(['key' => 'term_card_layout'], ['value' => 'swallow']);

        return compact('school', 'offering', 'top', 'low');
    }

    #[Test]
    public function the_swallow_layout_generates_the_term_report_pdf(): void
    {
        ['offering' => $offering, 'top' => $top] = $this->scoredClass();
        $enrollment = Enrollment::where('offering_id', $offering->id)->where('user_id', $top->id)->firstOrFail();

        $response = $this->actingAs($this->headmaster)->get(route('term-report.print', [$enrollment->id, $this->term->id]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    #[Test]
    public function subjects_follow_the_schools_preprinted_form_order(): void
    {
        ['school' => $school, 'offering' => $offering, 'top' => $top] = $this->scoredClass();

        // Attach extra subjects out of form order; the card must reorder them.
        foreach (['Religious Knowledge', 'English language'] as $name) {
            $s = Subject::factory()->create(['name' => $name, 'counts_toward_total' => false]);
            $offering->subjects($this->term->id)->save($s, ['term_id' => $this->term->id]);
        }

        $enrollment = Enrollment::where('offering_id', $offering->id)->where('user_id', $top->id)->firstOrFail();
        $reports = (new ReportGeneratorService())->generateStudentReportPdf(
            new NewTermReportRepository($enrollment, $this->term, $school),
        );
        $html = view('print.termReport-swallow', ['reports' => $reports, 'positions' => collect(), 'classSize' => 1])->render();

        // Form order: Religious Knowledge before English language before Mathematics.
        $this->assertTrue(
            strpos($html, 'Religious Knowledge') < strpos($html, 'English language')
            && strpos($html, 'English language') < strpos($html, 'Mathematics'),
            'Subjects are not in the pre-printed form order',
        );
    }

    #[Test]
    public function the_card_auto_fills_position_average_class_size_and_grade_remark(): void
    {
        ['school' => $school, 'offering' => $offering, 'top' => $top] = $this->scoredClass();
        $enrollment = Enrollment::where('offering_id', $offering->id)->where('user_id', $top->id)->firstOrFail();

        $reports = (new ReportGeneratorService())->generateStudentReportPdf(
            new NewTermReportRepository($enrollment, $this->term, $school),
        );
        $ranked = (new PositionService())->rankedReports($offering, $this->term, $school)->keyBy('student_id');

        $html = view('print.termReport-swallow', [
            'reports' => $reports,
            'positions' => $ranked,
            'classSize' => $ranked->count(),
        ])->render();

        $this->assertStringContainsString('Position:</strong> 1', $html);       // top pupil ranks first
        $this->assertStringContainsString('No. in class:</strong> 2', $html);
        $this->assertStringContainsString('Average:</strong> 80', $html);
        $this->assertStringContainsString('A Excellent', $html);                // 80% → grade A / Excellent
    }
}
