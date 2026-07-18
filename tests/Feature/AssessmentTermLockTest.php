<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Grade;
use App\Models\Offering;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssessmentTermLockTest extends TestCase
{
    use RefreshDatabase;

    private Term $closedTerm;
    private Term $openTerm;
    private AssessmentType $type;
    private Offering $offering;
    private Subject $subject;

    public function setUp(): void
    {
        parent::setUp();

        $this->closedTerm = Term::create([
            'name' => 'Term 1',
            'start' => '2025-09-01',
            'end' => '2026-01-31',
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $this->openTerm = Term::create([
            'name' => 'Term 2',
            'start' => '2026-02-01',
            'end' => '2099-12-31',
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $school = \App\Models\School::create(['name' => 'S']);
        $this->type = AssessmentType::create([
            'school_id' => $school->id,
            'name' => 'Test',
            'weight' => 0.25,
            'display_order' => 1,
        ]);
        $this->subject = Subject::create(['name' => 'Mathematics']);
        $this->offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id' => Grade::factory()->create()->id,
        ]);

        // The teacher must be attached to the offering to be allowed to grade it
        // (canEnterGradesFor); without this they are forbidden regardless of term.
        \Illuminate\Support\Facades\DB::table('teacher_offering')->insert([
            'user_id' => $this->teacher->id,
            'offering_id' => $this->offering->id,
            'principal' => false,
        ]);
    }

    #[Test]
    public function teacher_cannot_create_an_assessment_in_a_closed_term()
    {
        $this->actingAs($this->teacher)
            ->post(route('reporting.assessment.store'), [
                'assessment_type_id' => $this->type->id,
                'offering_id' => $this->offering->id,
                'term_id' => $this->closedTerm->id,
                'subject_id' => $this->subject->id,
                'name' => 'Late entry',
                'date' => '2026-01-15',
                'max_score' => 20,
            ])
            ->assertForbidden();
    }

    #[Test]
    public function teacher_can_create_an_assessment_in_the_open_term()
    {
        $this->actingAs($this->teacher)
            ->post(route('reporting.assessment.store'), [
                'assessment_type_id' => $this->type->id,
                'offering_id' => $this->offering->id,
                'term_id' => $this->openTerm->id,
                'subject_id' => $this->subject->id,
                'name' => 'Quiz',
                'date' => '2026-02-15',
                'max_score' => 20,
            ])
            ->assertRedirect();
    }

    #[Test]
    public function headmaster_can_still_create_in_a_closed_term()
    {
        $this->actingAs($this->headmaster)
            ->post(route('reporting.assessment.store'), [
                'assessment_type_id' => $this->type->id,
                'offering_id' => $this->offering->id,
                'term_id' => $this->closedTerm->id,
                'subject_id' => $this->subject->id,
                'name' => 'Headmaster correction',
                'date' => '2026-01-15',
                'max_score' => 20,
            ])
            ->assertRedirect();
    }

    #[Test]
    public function teacher_cannot_save_scores_on_a_closed_term_assessment()
    {
        $assessment = Assessment::create([
            'assessment_type_id' => $this->type->id,
            'offering_id' => $this->offering->id,
            'term_id' => $this->closedTerm->id,
            'subject_id' => $this->subject->id,
            'name' => 'Old quiz',
            'max_score' => 20,
            'confirmed' => true,
        ]);

        $this->actingAs($this->teacher)
            ->post(route('reporting.assessment.saveScores', $assessment), [
                'scores' => [$this->student->id => 18],
            ])
            ->assertForbidden();
    }

    #[Test]
    public function teacher_cannot_destroy_a_closed_term_assessment()
    {
        $assessment = Assessment::create([
            'assessment_type_id' => $this->type->id,
            'offering_id' => $this->offering->id,
            'term_id' => $this->closedTerm->id,
            'subject_id' => $this->subject->id,
            'name' => 'Old quiz',
            'max_score' => 20,
            'confirmed' => true,
        ]);

        $this->actingAs($this->teacher)
            ->delete(route('reporting.assessment.delete', $assessment))
            ->assertForbidden();
    }

    #[Test]
    public function term_isLocked_is_true_only_after_end_date()
    {
        $this->assertTrue($this->closedTerm->isLocked());
        $this->assertFalse($this->openTerm->isLocked());
    }

    #[Test]
    public function a_nat_assessment_is_created_without_a_term()
    {
        // A zero-weight type (NAT) is term-less: no term_id required, no term-lock check.
        $natType = AssessmentType::create([
            'school_id' => $this->type->school_id,
            'name' => 'National Assessment Test',
            'weight' => 0,
            'display_order' => 9,
        ]);

        $this->actingAs($this->headmaster)
            ->post(route('reporting.assessment.store'), [
                'assessment_type_id' => $natType->id,
                'offering_id' => $this->offering->id,
                // no term_id
                'subject_id' => $this->subject->id,
                'name' => 'NAT Mathematics',
                'max_score' => 100,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('assessments', [
            'assessment_type_id' => $natType->id,
            'offering_id' => $this->offering->id,
            'subject_id' => $this->subject->id,
            'term_id' => null,
        ]);
    }

    #[Test]
    public function nat_scores_can_be_saved_even_though_there_is_no_term()
    {
        // term-less NAT assessment: saving its scores must not hit the term-lock check.
        $natType = AssessmentType::create(['school_id' => $this->type->school_id, 'name' => 'National Assessment Test', 'weight' => 0, 'display_order' => 9]);
        $nat = Assessment::create([
            'assessment_type_id' => $natType->id, 'offering_id' => $this->offering->id,
            'term_id' => null, 'subject_id' => $this->subject->id, 'name' => 'NAT Maths', 'max_score' => 100,
        ]);

        $this->actingAs($this->headmaster)
            ->post(route('reporting.assessment.saveScores', $nat), ['scores' => [$this->student->id => 80]])
            ->assertRedirect(); // would TypeError on assertTermWritable(null) before the fix

        $this->assertDatabaseHas('assessment_scores', ['assessment_id' => $nat->id, 'user_id' => $this->student->id, 'score' => 80]);
    }

    #[Test]
    public function saving_scores_requires_a_score_or_absent_for_each_pupil()
    {
        $assessment = Assessment::create([
            'assessment_type_id' => $this->type->id, 'offering_id' => $this->offering->id,
            'term_id' => $this->openTerm->id, 'subject_id' => $this->subject->id, 'name' => 'Quiz', 'max_score' => 20,
        ]);

        // blank score, not marked absent -> rejected
        $this->actingAs($this->headmaster)
            ->post(route('reporting.assessment.saveScores', $assessment), ['scores' => [$this->student->id => '']])
            ->assertSessionHasErrors();

        // marked absent -> accepted (stored as absent, no score)
        $this->actingAs($this->headmaster)
            ->post(route('reporting.assessment.saveScores', $assessment), ['scores' => [$this->student->id => ''], 'absent' => [$this->student->id => 1]])
            ->assertRedirect();
        $this->assertDatabaseHas('assessment_scores', ['assessment_id' => $assessment->id, 'user_id' => $this->student->id, 'absent' => true]);
    }
}
