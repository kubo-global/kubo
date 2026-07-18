<?php

namespace Tests\Feature;

use App\Models\AssessmentType;
use App\Models\Grade;
use App\Models\Offering;
use App\Models\School;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssessmentDefaultMaxScoreTest extends TestCase
{
    use RefreshDatabase;

    private AssessmentType $type;
    private Offering $offering;
    private Subject $subject;

    public function setUp(): void
    {
        parent::setUp();

        $school = School::create(['name' => 'S']);
        $this->type = AssessmentType::create([
            'school_id' => $school->id,
            'name' => 'Test',
            'weight' => 0.25,
            'default_max_score' => 20,
            'display_order' => 1,
        ]);
        $this->subject = Subject::create(['name' => 'Mathematics']);
        $this->offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id' => Grade::factory()->create()->id,
        ]);

        // Add teacher to offering so create() returns offerings list.
        \Illuminate\Support\Facades\DB::table('teacher_offering')->insert([
            'user_id' => $this->teacher->id,
            'offering_id' => $this->offering->id,
            'principal' => true,
        ]);
    }

    #[Test]
    public function create_form_pre_fills_max_score_from_assessment_type_default()
    {
        $response = $this->actingAs($this->teacher)
            ->get(route('reporting.assessment.create', ['type' => $this->type->id]));

        $response->assertOk();
        $response->assertSee('value="20"', false);
    }

    #[Test]
    public function admin_can_update_default_max_score_via_settings()
    {
        $this->actingAs($this->admin)
            ->post(route('settings.update-assessment-type', $this->type), [
                'weight_percent' => 30,
                'default_max_score' => 25,
            ])
            ->assertRedirect();

        $this->type->refresh();
        $this->assertSame(25, (int) $this->type->default_max_score);
        // Stored as a 0–1 decimal even though the form takes a percent.
        $this->assertSame('0.3000', (string) $this->type->weight);
    }

    #[Test]
    public function teacher_can_still_override_max_score_per_assessment()
    {
        // The form lets the teacher type any value; the controller only
        // enforces required + integer + min:1, not that it match the default.
        $this->actingAs($this->teacher)
            ->post(route('reporting.assessment.store'), [
                'assessment_type_id' => $this->type->id,
                'offering_id' => $this->offering->id,
                'term_id' => \App\Models\Term::create([
                    'name' => 'Current Term',
                    'start' => '2026-02-01',
                    'end' => '2099-12-31',
                    'schoolyear_id' => $this->schoolyear->id,
                ])->id,
                'subject_id' => $this->subject->id,
                'name' => 'Custom-max quiz',
                'date' => now()->toDateString(),
                'max_score' => 50, // overriding the default of 20
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('assessments', [
            'name' => 'Custom-max quiz',
            'max_score' => 50,
        ]);
    }
}
