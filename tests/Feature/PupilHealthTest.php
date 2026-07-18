<?php

namespace Tests\Feature;

use App\Livewire\PupilHealth;
use App\Models\Enrollment;
use App\Models\HealthReport;
use App\Models\IncidentReport;
use App\Models\MedicalNote;
use App\Models\Offering;
use App\Models\Student;
use App\Models\StudentHealthMilestone;
use App\Models\WoundCase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The pupil's health record: every entry type is recorded and edited in place,
 * without leaving the timeline.
 */
class PupilHealthTest extends TestCase
{
    use RefreshDatabase;

    private Student $pupil;

    public function setUp(): void
    {
        parent::setUp();
        $this->pupil = Student::factory()->create(['first_name' => 'Awa', 'last_name' => 'Dukureh']);
    }

    private function desk()
    {
        return Livewire::actingAs($this->headmaster)->test(PupilHealth::class, ['user' => $this->pupil]);
    }

    #[Test]
    public function a_checkup_is_recorded_in_place_and_appears_in_the_timeline(): void
    {
        $this->desk()
            ->call('start', 'checkup')
            ->set('height', 132)
            ->set('weight', 28.5)          // kilograms, as the label says
            ->set('teeth', 'Good')
            ->set('generalCondition', 'Generally healthy')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('type', null)      // the panel closed again
            ->assertSee('Generally healthy'); // and the entry is in the timeline

        $report = HealthReport::where('user_id', $this->pupil->id)->sole();
        $this->assertSame(132, $report->height_in_cm);
        $this->assertSame(28500, $report->weight_in_gram);
        $this->assertSame('Good', $report->teeth_condition);
    }

    #[Test]
    public function a_checkup_with_nonsense_measurements_is_refused(): void
    {
        $this->desk()
            ->call('start', 'checkup')
            ->set('height', 1300)
            ->set('weight', 28000)
            ->call('save')
            ->assertHasErrors(['height', 'weight'])
            ->assertSet('type', 'checkup'); // panel stays open with the input

        $this->assertSame(0, HealthReport::count());
    }

    #[Test]
    public function a_new_checkup_records_the_milestones_ticked_alongside_it(): void
    {
        $this->desk()
            ->call('start', 'checkup')
            ->set('height', 130)
            ->set('milestones.polio-vax', true)
            ->call('save')
            ->assertHasNoErrors();

        $milestone = StudentHealthMilestone::where('user_id', $this->pupil->id)->sole();
        $this->assertNotNull($milestone->polio_received_on);
        $this->assertNull($milestone->tetanus_received_on);
    }

    #[Test]
    public function a_checkup_that_ticks_no_milestone_leaves_no_empty_milestone_row(): void
    {
        $this->desk()
            ->call('start', 'checkup')
            ->set('height', 130)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0, StudentHealthMilestone::where('user_id', $this->pupil->id)->count());
    }

    #[Test]
    public function an_incident_is_recorded_in_place_with_medication_and_parent_contact(): void
    {
        $this->desk()
            ->call('start', 'incident')
            ->set('complaint', 'Headache and fever')
            ->set('medicationGiven', 'Paracetamol 250 mg')
            ->set('parentsContacted', true)
            ->set('needsFollowUp', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Headache and fever');

        $incident = IncidentReport::where('user_id', $this->pupil->id)->sole();
        $this->assertSame('Paracetamol 250 mg', $incident->medication_given);
        $this->assertTrue($incident->parents_contacted);
        $this->assertTrue($incident->isOpen());
        $this->assertSame($this->headmaster->id, $incident->recorded_by);
    }

    #[Test]
    public function an_incident_without_a_complaint_is_refused(): void
    {
        $this->desk()
            ->call('start', 'incident')
            ->call('save')
            ->assertHasErrors(['complaint']);

        $this->assertSame(0, IncidentReport::count());
    }

    #[Test]
    public function an_existing_entry_is_edited_in_place(): void
    {
        $report = HealthReport::create([
            'user_id' => $this->pupil->id,
            'general_condition' => 'Healthy',
            'height_in_cm' => 130,
            'weight_in_gram' => 28000,
        ]);

        $this->desk()
            ->call('edit', 'checkup', $report->id)
            ->assertSet('height', 130)
            ->assertSet('weight', 28.0)     // grams on the way in, kg on the form
            ->set('height', 133)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('type', null);

        $this->assertSame(133, $report->fresh()->height_in_cm);
        $this->assertSame(1, HealthReport::count()); // edited, not duplicated
    }

    #[Test]
    public function a_wound_case_can_be_opened_with_its_first_treatment(): void
    {
        $this->desk()
            ->call('start', 'wound')
            ->set('diagnosis', 'Cut on the knee')
            ->set('firstVisitTreatment', 'Cleaned and dressed')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Cut on the knee');

        $case = WoundCase::where('user_id', $this->pupil->id)->sole();
        $this->assertTrue($case->isOpen());
        $this->assertSame('Cleaned and dressed', $case->visits->sole()->treatment);
    }

    #[Test]
    public function an_open_incident_can_be_closed_from_its_timeline_card(): void
    {
        $incident = IncidentReport::create([
            'user_id' => $this->pupil->id,
            'occurred_at' => now()->subDay(),
            'complaint' => 'Fell during break',
            'closed_on' => null,
        ]);

        $this->desk()
            ->assertSee('open · follow up')
            ->call('closeIncident', $incident->id)
            ->assertDontSee('open · follow up');

        $this->assertFalse($incident->fresh()->isOpen());
    }

    #[Test]
    public function an_open_incident_collects_follow_ups_until_it_is_closed(): void
    {
        $incident = IncidentReport::create([
            'user_id' => $this->pupil->id,
            'occurred_at' => now()->subDays(2),
            'complaint' => 'Fell during break and hurt the elbow',
            'closed_on' => null,
        ]);

        $this->desk()
            ->call('followUp', $incident->id)
            ->assertSet('type', 'follow-up')
            ->set('content', 'Elbow checked, swelling gone')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Elbow checked, swelling gone')  // listed under the incident
            ->assertSee('open · follow up');             // and it is still open

        $followUp = $incident->followUps()->sole();
        $this->assertSame('Elbow checked, swelling gone', $followUp->note);
        $this->assertSame($this->headmaster->id, $followUp->recorded_by);
        $this->assertTrue($incident->fresh()->isOpen());
    }

    #[Test]
    public function a_follow_up_needs_something_written_in_it(): void
    {
        $incident = IncidentReport::create([
            'user_id' => $this->pupil->id,
            'occurred_at' => now(),
            'complaint' => 'Headache',
        ]);

        $this->desk()
            ->call('followUp', $incident->id)
            ->call('save')
            ->assertHasErrors(['content']);

        $this->assertSame(0, $incident->followUps()->count());
    }

    #[Test]
    public function an_open_wound_case_can_be_closed_from_its_timeline_card(): void
    {
        $case = WoundCase::create([
            'user_id' => $this->pupil->id,
            'opened_on' => now()->subWeek(),
            'diagnosis' => 'Cut on the knee',
            'closed_on' => null,
        ]);

        $this->desk()->call('closeWound', $case->id);

        $this->assertFalse($case->fresh()->isOpen());
    }

    #[Test]
    public function a_medical_note_is_recorded_in_place(): void
    {
        $this->desk()
            ->call('start', 'note')
            ->set('content', 'Complains of stomach ache after lunch')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Complains of stomach ache after lunch');

        $this->assertSame(1, MedicalNote::where('user_id', $this->pupil->id)->count());
    }

    #[Test]
    public function a_pupil_record_only_ever_edits_that_pupils_entries(): void
    {
        $other = Student::factory()->create();
        $theirs = HealthReport::create(['user_id' => $other->id, 'general_condition' => 'Theirs']);

        // Every lookup is scoped to this pupil, so another pupil's id finds nothing
        // (a 404 over HTTP; the exception surfaces directly in a component test).
        $this->expectException(ModelNotFoundException::class);

        $this->desk()->call('edit', 'checkup', $theirs->id);
    }

    #[Test]
    public function a_teacher_without_health_access_cannot_open_the_record(): void
    {
        Livewire::actingAs($this->teacher)
            ->test(PupilHealth::class, ['user' => $this->pupil])
            ->assertStatus(403);
    }

    #[Test]
    public function the_record_has_its_own_page_inside_health(): void
    {
        // It lives under Health (not as a tab on the student page), so following a
        // pupil from the desk keeps you in the section you were working in.
        $this->actingAs($this->headmaster)
            ->get(route('health.pupil', $this->pupil))
            ->assertOk()
            ->assertSee('Dukureh')                      // the pupil this record is about
            ->assertSee('Back to the health desk')
            ->assertSee(route('health.index'), false);

        $this->actingAs($this->teacher)
            ->get(route('health.pupil', $this->pupil))
            ->assertForbidden();
    }

    #[Test]
    public function the_student_page_points_at_the_health_record_instead_of_holding_it(): void
    {
        // The student page only renders for an enrolled pupil.
        Enrollment::factory()->create([
            'user_id' => $this->pupil->id,
            'offering_id' => Offering::factory()->create()->id,
        ]);

        $this->actingAs($this->headmaster)
            ->get(route('students.show', $this->pupil))
            ->assertOk()
            ->assertSee('Open health record')
            ->assertSee(route('health.pupil', $this->pupil), false);

        // A teacher without health access sees no way in.
        $this->actingAs($this->teacher)
            ->get(route('students.show', $this->pupil))
            ->assertOk()
            ->assertDontSee('Open health record');
    }
}
