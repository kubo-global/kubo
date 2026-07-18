<?php

namespace Tests\Feature;

use App\Livewire\HealthDesk;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\HealthReport;
use App\Models\IncidentReport;
use App\Models\Offering;
use App\Models\Schoolyear;
use App\Models\Student;
use App\Models\WoundCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HealthListsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_checkup_view_is_newest_first_and_filters_by_class(): void
    {
        $year = Schoolyear::create(['name' => '2025-2026', 'start' => now()->subMonths(3), 'end' => now()->addMonths(6)]);
        $g3 = Grade::factory()->create(['name' => 'Grade 3']);
        $g1 = Grade::factory()->create(['name' => 'Grade 1']);
        $o3 = Offering::factory()->create(['schoolyear_id' => $year->id, 'grade_id' => $g3->id]);
        $o1 = Offering::factory()->create(['schoolyear_id' => $year->id, 'grade_id' => $g1->id]);
        $p3 = Student::factory()->create(['first_name' => 'Awa', 'last_name' => 'Dukureh']);
        $p1 = Student::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones']);
        Enrollment::factory()->create(['user_id' => $p3->id, 'offering_id' => $o3->id]);
        Enrollment::factory()->create(['user_id' => $p1->id, 'offering_id' => $o1->id]);

        $old = HealthReport::create(['user_id' => $p3->id, 'general_condition' => 'OLD CONDITION']);
        HealthReport::create(['user_id' => $p3->id, 'general_condition' => 'NEW CONDITION']);
        DB::table('health_reports')->where('id', $old->id)->update(['created_at' => now()->subYear()]);

        $this->actingAs($this->headmaster);

        Livewire::test(HealthDesk::class)
            ->set('view', 'checkups')
            ->assertSee('Grade 3')                                 // class column
            ->assertSeeInOrder(['NEW CONDITION', 'OLD CONDITION'])  // newest first
            ->set('search', 'Grade 3')                             // filter by class
            ->assertSee('Awa')
            ->assertDontSee('Bob');
    }

    #[Test]
    public function the_follow_up_view_shows_only_open_work_and_can_close_it(): void
    {
        $pupil = Student::factory()->create(['first_name' => 'Awa', 'last_name' => 'Dukureh']);
        $open = WoundCase::create(['user_id' => $pupil->id, 'opened_on' => now()->subDays(3), 'diagnosis' => 'Cut on the knee', 'closed_on' => null]);
        WoundCase::create(['user_id' => $pupil->id, 'opened_on' => now()->subMonth(), 'diagnosis' => 'Old graze', 'closed_on' => now()->subWeeks(2)]);

        $this->actingAs($this->headmaster);

        // The worklist holds the open case. (The closed one can still surface under
        // "Recently recorded" below it — that section is a pulse, not a worklist.)
        Livewire::test(HealthDesk::class)
            ->assertSee('Needs follow-up')
            ->assertSee('Cut on the knee')
            // Closing it from the desk empties the worklist...
            ->call('closeWound', $open->id)
            ->assertSee('Nothing open')
            // ...but the case is still there under the wound-case view.
            ->set('view', 'wounds')
            ->assertSee('Cut on the knee')
            ->assertSee('Old graze');

        $this->assertFalse($open->fresh()->isOpen());
    }

    #[Test]
    public function the_incident_view_lists_incidents(): void
    {
        $pupil = Student::factory()->create(['first_name' => 'Musa', 'last_name' => 'Ceesay']);
        IncidentReport::create([
            'user_id' => $pupil->id, 'recorded_by' => $this->headmaster->id,
            'occurred_at' => now()->subDay(), 'location' => 'Playground',
            'complaint' => 'Fell and hurt the elbow', 'first_aid_given' => true,
            'closed_on' => now()->subDay(),
        ]);

        $this->actingAs($this->headmaster);

        Livewire::test(HealthDesk::class)
            ->set('view', 'incidents')
            ->assertSee('Fell and hurt the elbow')
            ->assertSee('Ceesay')
            ->assertSee('first aid');
    }

    #[Test]
    public function the_pupil_search_finds_a_pupil_and_links_to_their_record(): void
    {
        $year = Schoolyear::create(['name' => '2025-2026', 'start' => now()->subMonths(3), 'end' => now()->addMonths(6)]);
        $offering = Offering::factory()->create(['schoolyear_id' => $year->id]);
        $pupil = Student::factory()->create(['first_name' => 'Awa', 'last_name' => 'Dukureh']);
        Enrollment::factory()->create(['user_id' => $pupil->id, 'offering_id' => $offering->id]);

        $this->actingAs($this->headmaster);

        Livewire::test(HealthDesk::class)
            ->set('search', 'Dukureh')
            ->assertSee('Dukureh')
            ->assertSee(route('health.pupil', $pupil->id), false);
    }

    #[Test]
    public function the_old_list_urls_redirect_into_the_desk(): void
    {
        $this->actingAs($this->headmaster)
            ->get(route('health.incidents.index'))
            ->assertRedirect(route('health.index', ['view' => 'incidents']));

        $this->actingAs($this->headmaster)
            ->get(route('health.wound-cases.index'))
            ->assertRedirect(route('health.index', ['view' => 'wounds']));
    }

    #[Test]
    public function a_wound_visit_can_be_edited(): void
    {
        $pupil = Student::factory()->create();
        $case = WoundCase::create(['user_id' => $pupil->id, 'opened_on' => now()->subDays(5), 'diagnosis' => 'Cut']);
        $visit = $case->visits()->create(['recorded_by' => $this->headmaster->id, 'visited_on' => now()->subDays(4), 'treatment' => 'Cleaned', 'remarks' => '']);

        $this->actingAs($this->headmaster)
            ->put(route('health.wound-cases.update-visit', $visit), [
                'visited_on' => now()->subDays(2)->toDateString(),
                'treatment' => 'Re-dressed the wound',
                'remarks' => 'Healing well',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('wound_care_visits', [
            'id' => $visit->id, 'treatment' => 'Re-dressed the wound', 'remarks' => 'Healing well',
        ]);
    }

    #[Test]
    public function a_plain_teacher_cannot_see_the_health_desk(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('health.index'))
            ->assertForbidden();

        $this->actingAs($this->teacher)
            ->get(route('health.wound-cases.index'))
            ->assertForbidden();
    }
}
