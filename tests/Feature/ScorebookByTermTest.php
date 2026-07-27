<?php

namespace Tests\Feature;

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
use App\Domain\Reporting\Services\PositionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The "By term" scorebook view, the per-school period mode, and the per-school
 * report-card routing (Albreda's WAEC card).
 */
class ScorebookByTermTest extends TestCase
{
    use RefreshDatabase;

    /** A Grade 4 class with a mark-added (Maths) and a graded (Arts) subject, two scored pupils. */
    private function scoredClass(): array
    {
        $school = School::first() ?? School::factory()->create();
        $test = AssessmentType::factory()->test()->create(['school_id' => $school->id]);
        AssessmentType::factory()->exam()->create(['school_id' => $school->id]);

        $offering = Offering::factory()->create(['schoolyear_id' => $this->schoolyear->id, 'grade_id' => Grade::factory()->create(['name' => 'Grade 4'])->id]);
        $maths = Subject::factory()->create(['name' => 'Mathematics', 'counts_toward_total' => true]);
        $arts = Subject::factory()->create(['name' => 'Arts', 'counts_toward_total' => false]);
        $offering->subjects($this->term->id)->save($maths, ['term_id' => $this->term->id]);
        $offering->subjects($this->term->id)->save($arts, ['term_id' => $this->term->id]);

        $mathsA = Assessment::factory()->create(['assessment_type_id' => $test->id, 'offering_id' => $offering->id, 'term_id' => $this->term->id, 'subject_id' => $maths->id, 'max_score' => 100]);

        $top = Student::factory()->create(['first_name' => 'Top', 'last_name' => 'Pupil']);
        $low = Student::factory()->create(['first_name' => 'Low', 'last_name' => 'Pupil']);
        Enrollment::factory()->create(['user_id' => $top->id, 'offering_id' => $offering->id]);
        Enrollment::factory()->create(['user_id' => $low->id, 'offering_id' => $offering->id]);
        AssessmentScore::factory()->create(['user_id' => $top->id, 'assessment_id' => $mathsA->id, 'score' => 90]);
        AssessmentScore::factory()->create(['user_id' => $low->id, 'assessment_id' => $mathsA->id, 'score' => 40]);

        return compact('offering', 'maths', 'arts', 'top', 'low', 'school');
    }

    #[Test]
    public function the_by_term_view_ranks_pupils_and_shows_the_toggle(): void
    {
        ['offering' => $offering] = $this->scoredClass();

        $this->actingAs($this->headmaster)
            ->get(route('term-grid.overview', ['offering' => $offering, 'term' => $this->term->id]))
            ->assertOk()
            ->assertSee('By term')
            ->assertSeeInOrder(['Top Pupil', 'Low Pupil']); // higher term total ranked first
    }

    #[Test]
    public function the_period_mode_switches_the_scorebook_to_test1_test2_exam(): void
    {
        ['offering' => $offering, 'school' => $school] = $this->scoredClass();
        $school->configs()->updateOrCreate(['key' => 'scorebook_period_mode'], ['value' => 'tests']);

        $this->actingAs($this->headmaster)
            ->get(route('term-grid.report', ['offering' => $offering, 'term' => $this->term->id]))
            ->assertOk()
            ->assertSee('Test 1'); // period selector uses Test 1 / Test 2 / Exam, not month names
    }

    #[Test]
    public function the_albreda_card_layout_generates_the_term_report_pdf(): void
    {
        ['offering' => $offering, 'top' => $top, 'school' => $school] = $this->scoredClass();
        $school->configs()->updateOrCreate(['key' => 'term_card_layout'], ['value' => 'albreda']);
        GradingScale::create(['school_id' => $school->id, 'purpose' => null, 'label' => 'A1', 'min_score' => 80, 'max_score' => 100]);

        $enrollment = Enrollment::where('offering_id', $offering->id)->where('user_id', $top->id)->firstOrFail();

        $response = $this->actingAs($this->headmaster)->get(route('term-report.print', [$enrollment->id, $this->term->id]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    #[Test]
    public function a_pupil_with_no_marks_does_not_sit_and_is_left_out_of_the_ranking_and_count(): void
    {
        ['offering' => $offering, 'school' => $school] = $this->scoredClass();

        // A third pupil, enrolled but never assessed.
        $ghost = Student::factory()->create(['first_name' => 'Ghost', 'last_name' => 'Pupil']);
        Enrollment::factory()->create(['user_id' => $ghost->id, 'offering_id' => $offering->id]);

        $ranked = (new PositionService())->rank($offering, $this->term, $school);

        // Only the two who sat are ranked; the ghost is neither counted nor ranked.
        $this->assertSame(2, $ranked->count());
        $this->assertNull($ranked->firstWhere('student_id', $ghost->id));

        // And they get no report card / no row in the by-term view.
        $this->actingAs($this->headmaster)
            ->get(route('term-grid.overview', ['offering' => $offering, 'term' => $this->term->id]))
            ->assertOk()
            ->assertDontSee('Ghost Pupil');
    }
}
