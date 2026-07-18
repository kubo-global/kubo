<?php

namespace Tests\Feature;

use App\Models\IncidentReport;
use App\Models\MedicalNote;
use App\Models\User;
use App\Models\WoundCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * End-to-end smoke for the three new health record types — incidents,
 * wound cases (with visits), and medical notes. Covers the controller
 * round-trip and verifies the unified timeline view renders them.
 */
class HealthRecordTypesTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        // TestCase already provides $this->student (with student role)
    }

    #[Test]
    public function caregiver_can_record_an_incident()
    {
        $caregiver = User::create(['first_name' => 'Care', 'last_name' => 'Giver', 'password' => bcrypt('x')]);
        $caregiver->assignRole('caregiver');

        $this->actingAs($caregiver)
            ->post(route('health.incidents.store'), [
                'student' => $this->student->id,
                'occurred_at' => '2026-05-20T10:30',
                'location' => 'Playground',
                'temperature' => 37.2,
                'complaint' => 'Fell off the swing',
                'action_taken' => 'Cleaned scrape, gave bandage',
                'first_aid_given' => '1',
                'sent_home' => '0',
                'taken_to_hospital' => '0',
            ])
            ->assertRedirect();

        $incident = IncidentReport::first();
        $this->assertNotNull($incident);
        $this->assertEquals($this->student->id, $incident->user_id);
        $this->assertEquals($caregiver->id, $incident->recorded_by);
        $this->assertTrue($incident->first_aid_given);
        $this->assertFalse($incident->sent_home);
    }

    #[Test]
    public function an_incident_records_medication_and_whether_the_parents_were_contacted()
    {
        $this->actingAs($this->headmaster)
            ->post(route('health.incidents.store'), [
                'student' => $this->student->id,
                'occurred_at' => '2026-05-20T10:30',
                'complaint' => 'Headache and fever',
                'medication_given' => 'Paracetamol 250 mg',
                'parents_contacted' => '1',
                'sent_home' => '1',
            ])
            ->assertRedirect();

        $incident = IncidentReport::first();
        $this->assertSame('Paracetamol 250 mg', $incident->medication_given);
        $this->assertTrue($incident->parents_contacted);
        $this->assertStringContainsString('medication: Paracetamol 250 mg', $incident->actionLabel());
        $this->assertStringContainsString('parents contacted', $incident->actionLabel());
    }

    #[Test]
    public function an_incident_is_closed_on_the_spot_unless_it_needs_follow_up()
    {
        $post = fn (array $extra) => $this->actingAs($this->headmaster)->post(route('health.incidents.store'), array_merge([
            'student' => $this->student->id,
            'occurred_at' => '2026-05-20T10:30',
            'complaint' => 'Stomach ache',
        ], $extra))->assertRedirect();

        // Handled there and then: closed immediately, so it stays off the worklist.
        $post([]);
        $this->assertFalse(IncidentReport::first()->isOpen());

        // Ticked as needing follow-up: stays open.
        $post(['needs_follow_up' => '1']);
        $open = IncidentReport::latest('id')->first();
        $this->assertTrue($open->isOpen());

        $this->actingAs($this->headmaster)
            ->post(route('health.incidents.close', $open))
            ->assertRedirect();

        $this->assertFalse($open->fresh()->isOpen());
        $this->assertSame(0, IncidentReport::whereNull('closed_on')->count());
    }

    #[Test]
    public function a_closed_incident_can_be_reopened_for_follow_up()
    {
        $incident = IncidentReport::create([
            'user_id' => $this->student->id,
            'occurred_at' => now()->subDay(),
            'complaint' => 'Twisted ankle',
            'closed_on' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($this->headmaster)
            ->put(route('health.incidents.update', $incident), [
                'occurred_at' => $incident->occurred_at->format('Y-m-d\TH:i'),
                'complaint' => 'Twisted ankle, still limping',
                'needs_follow_up' => '1',
            ])
            ->assertRedirect();

        $this->assertTrue($incident->fresh()->isOpen());
    }

    #[Test]
    public function opening_a_wound_case_with_first_visit_creates_one_visit_row()
    {
        $this->actingAs($this->headmaster)
            ->post(route('health.wound-cases.store'), [
                'student' => $this->student->id,
                'opened_on' => '2026-05-15',
                'diagnosis' => 'Cut on left foot',
                'first_visit_treatment' => 'Cleaned and bandaged',
                'first_visit_remarks' => 'visit again',
            ])
            ->assertRedirect();

        $case = WoundCase::first();
        $this->assertNotNull($case);
        $this->assertCount(1, $case->visits);
        $this->assertEquals('Cleaned and bandaged', $case->visits->first()->treatment);
    }

    #[Test]
    public function visits_can_be_added_to_an_existing_case()
    {
        $case = WoundCase::create([
            'user_id' => $this->student->id,
            'opened_on' => '2026-05-15',
            'diagnosis' => 'Cut on left foot',
        ]);

        $this->actingAs($this->headmaster)
            ->post(route('health.wound-cases.add-visit', $case), [
                'visited_on' => '2026-05-17',
                'treatment' => 'Re-bandaged',
                'remarks' => 'looking better',
            ])
            ->assertRedirect();

        $this->assertCount(1, $case->visits()->get());
    }

    #[Test]
    public function closing_a_wound_case_sets_closed_on()
    {
        $case = WoundCase::create([
            'user_id' => $this->student->id,
            'opened_on' => '2026-05-15',
            'diagnosis' => 'Cut on left foot',
        ]);

        $this->actingAs($this->headmaster)
            ->post(route('health.wound-cases.close', $case))
            ->assertRedirect();

        $this->assertNotNull($case->fresh()->closed_on);
        $this->assertFalse($case->fresh()->isOpen());
    }

    #[Test]
    public function medical_note_round_trips()
    {
        $this->actingAs($this->headmaster)
            ->post(route('health.notes.store'), [
                'student' => $this->student->id,
                'noted_on' => '2026-05-20',
                'content' => 'Complained of headache after PE',
                'temperature' => 37.0,
            ])
            ->assertRedirect();

        $note = MedicalNote::first();
        $this->assertEquals('Complained of headache after PE', $note->content);
        $this->assertEquals($this->student->id, $note->user_id);
    }

    #[Test]
    public function teacher_without_health_permission_cannot_record_an_incident()
    {
        $this->actingAs($this->teacher)
            ->post(route('health.incidents.store'), [
                'student' => $this->student->id,
                'occurred_at' => '2026-05-20T10:30',
                'complaint' => 'x',
            ])
            ->assertForbidden();
    }
}
