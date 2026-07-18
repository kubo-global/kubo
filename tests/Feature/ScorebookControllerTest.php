<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AssessmentType;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Offering;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScorebookControllerTest extends TestCase
{
    use RefreshDatabase;

    private function offering(?Grade $grade = null): Offering
    {
        return Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id' => ($grade ?? Grade::factory()->create())->id,
        ]);
    }

    #[Test]
    public function the_class_list_renders_the_current_years_classes(): void
    {
        $grade = Grade::factory()->create(['name' => 'Grade 3']);
        $this->offering($grade);

        $this->actingAs($this->headmaster)
            ->get(route('reporting.grades'))
            ->assertOk()
            ->assertSee('Grade 3')
            ->assertSee('Classes');
    }

    #[Test]
    public function the_class_overview_shows_a_subjects_term_assessments(): void
    {
        $offering = $this->offering();
        $subject = Subject::factory()->create(['name' => 'English language']);
        $offering->subjects($this->term->id)->save($subject, ['term_id' => $this->term->id]);

        $type = AssessmentType::factory()->test()->create(['school_id' => School::factory()->create()->id]);
        $assessment = Assessment::factory()->create([
            'assessment_type_id' => $type->id,
            'offering_id' => $offering->id,
            'term_id' => $this->term->id,
            'subject_id' => $subject->id,
            'name' => 'test 1',
        ]);
        $pupil = Student::factory()->create(['first_name' => 'Awa', 'last_name' => 'Dukureh']);
        Enrollment::factory()->create(['user_id' => $pupil->id, 'offering_id' => $offering->id]);
        AssessmentScore::factory()->create(['user_id' => $pupil->id, 'assessment_id' => $assessment->id, 'score' => 8]);

        $this->actingAs($this->headmaster)
            ->get(route('scorebook.class', $offering))
            ->assertOk()
            ->assertSee('English language')
            ->assertSee('Dukureh Awa')
            ->assertSee('test 1')
            ->assertSee('Tests/exams this term: 1'); // the tab count badge
    }

    #[Test]
    public function the_class_overview_defaults_to_the_current_term(): void
    {
        $offering = $this->offering();
        $subject = \App\Models\Subject::factory()->create(['name' => 'English language']);

        // a term we are currently in, alongside the (historical) TestCase term
        $current = \App\Models\Term::create([
            'name' => 'Term 2',
            'start' => now()->subMonth(),
            'end' => now()->addMonth(),
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $offering->subjects($current->id)->save($subject, ['term_id' => $current->id]);

        $type = AssessmentType::factory()->test()->create(['school_id' => School::factory()->create()->id]);
        $a = Assessment::factory()->create([
            'assessment_type_id' => $type->id, 'offering_id' => $offering->id,
            'term_id' => $current->id, 'subject_id' => $subject->id, 'name' => 'current-term quiz',
        ]);
        $pupil = Student::factory()->create();
        Enrollment::factory()->create(['user_id' => $pupil->id, 'offering_id' => $offering->id]);
        AssessmentScore::factory()->create(['user_id' => $pupil->id, 'assessment_id' => $a->id, 'score' => 7]);

        // no ?term -> should land on the current term and show its assessment
        $this->actingAs($this->headmaster)
            ->get(route('scorebook.class', $offering))
            ->assertOk()
            ->assertSee('current-term quiz');
    }

    #[Test]
    public function the_nat_page_lists_the_term_less_nat_assessments(): void
    {
        $offering = $this->offering();
        $natType = AssessmentType::factory()->create(['school_id' => School::factory()->create()->id, 'name' => 'National Assessment Test', 'weight' => 0]);
        $subject = Subject::factory()->create(['name' => 'Integrated studies']);
        $nat = Assessment::factory()->create([
            'assessment_type_id' => $natType->id,
            'offering_id' => $offering->id,
            'term_id' => null,
            'subject_id' => $subject->id,
            'max_score' => 100,
        ]);
        $pupil = Student::factory()->create(['first_name' => 'Aisha', 'last_name' => 'Conteh']);
        Enrollment::factory()->create(['user_id' => $pupil->id, 'offering_id' => $offering->id]);
        AssessmentScore::factory()->create(['user_id' => $pupil->id, 'assessment_id' => $nat->id, 'score' => 26]);

        $this->actingAs($this->headmaster)
            ->get(route('scorebook.nat', $offering))
            ->assertOk()
            ->assertSee('National Assessment Test')
            ->assertSee('Integrated studies')
            ->assertSee('Conteh Aisha')
            // each subject is editable: its column links to the score-entry grid
            ->assertSee(route('reporting.assessment.scores', $nat), false)
            ->assertSee('enter or edit its scores');
    }
}
