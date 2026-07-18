<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Offering;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\WoundCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_headmaster_dashboard_shows_a_school_overview(): void
    {
        $year = Schoolyear::create(['name' => '2025-2026', 'start' => now()->subMonths(3), 'end' => now()->addMonths(6)]);
        $term = Term::create(['name' => 'Term 2', 'start' => now()->subWeek(), 'end' => now()->addWeek(), 'schoolyear_id' => $year->id]);
        $grade = Grade::factory()->create(['name' => 'Grade 3']);
        $offering = Offering::factory()->create(['schoolyear_id' => $year->id, 'grade_id' => $grade->id]);
        $pupil = Student::factory()->create();
        Enrollment::factory()->create(['user_id' => $pupil->id, 'offering_id' => $offering->id]);

        // a test/exam entered this term shows in the per-class count
        $type = AssessmentType::factory()->test()->create(['school_id' => School::factory()->create()->id]);
        Assessment::create([
            'assessment_type_id' => $type->id, 'offering_id' => $offering->id, 'term_id' => $term->id,
            'subject_id' => Subject::factory()->create()->id, 'name' => 'Quiz', 'max_score' => 20,
        ]);

        // an open wound case surfaces a follow-up nudge for health staff
        WoundCase::create(['user_id' => $pupil->id, 'opened_on' => now()->subDays(2), 'diagnosis' => 'Cut', 'closed_on' => null]);

        $this->actingAs($this->headmaster)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Welcome back')
            ->assertSee('Students')      // a headline stat
            ->assertSee('Grade 3')       // the class appears in "classes at a glance"
            ->assertSee('1 student')
            ->assertSee('1 test/exam')   // tests/exams this term, per class
            ->assertSee('tests &amp; exams in Term 2', false)
            ->assertSee('open for scores') // term-lock status, in plain words
            ->assertSee('1 health follow-up'); // health nudge (headmaster has medical access)
    }

    #[Test]
    public function the_follow_up_nudge_is_hidden_from_users_without_health_access(): void
    {
        WoundCase::create(['user_id' => Student::factory()->create()->id, 'opened_on' => now(), 'diagnosis' => 'Cut', 'closed_on' => null]);

        $this->actingAs($this->teacher)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('health follow-up');
    }

    #[Test]
    public function the_rollover_nudge_shows_near_year_end_and_hides_otherwise(): void
    {
        $this->headmaster->givePermissionTo('manage rollover');

        // current year ends in 3 weeks (within the 6-week window) -> nudge shows
        Schoolyear::create(['name' => '2025-2026', 'start' => now()->subMonths(10), 'end' => now()->addWeeks(3)]);
        $this->actingAs($this->headmaster->fresh())
            ->get(route('home'))
            ->assertOk()
            ->assertSee('is ending soon');

        // a year with months left -> no nudge
        Schoolyear::query()->delete();
        Schoolyear::create(['name' => '2026-2027', 'start' => now()->subMonths(2), 'end' => now()->addMonths(6)]);
        $this->actingAs($this->headmaster->fresh())
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('is ending soon');
    }

    #[Test]
    public function a_plain_student_sees_only_the_learn_card(): void
    {
        $this->actingAs($this->student)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Learn')
            ->assertDontSee('Welcome back');
    }
}
