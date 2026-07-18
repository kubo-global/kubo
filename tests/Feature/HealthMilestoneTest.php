<?php

namespace Tests\Feature;

use App\Models\StudentHealthMilestone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HealthMilestoneTest extends TestCase
{
    use RefreshDatabase;

    private User $caregiver;
    private User $studentUser;

    public function setUp(): void
    {
        parent::setUp();

        $this->caregiver = User::create(['first_name' => 'Care', 'last_name' => 'Giver', 'password' => bcrypt('x')]);
        $this->caregiver->assignRole('caregiver');

        $this->studentUser = User::create(['first_name' => 'Test', 'last_name' => 'Student', 'password' => bcrypt('x')]);
        $this->studentUser->assignRole('student');
    }

    #[Test]
    public function storing_a_report_with_milestone_answers_creates_a_milestone_row()
    {
        $this->actingAs($this->caregiver)->post(route('health.store'), [
            'student' => $this->studentUser->id,
            'general-condition' => 'Healthy',
            'menstruated' => '1',
            'hep-a-vax' => '1',
        ]);

        $milestone = StudentHealthMilestone::where('user_id', $this->studentUser->id)->first();
        $this->assertNotNull($milestone);
        $this->assertNotNull($milestone->first_menstruated_on);
        $this->assertNotNull($milestone->hep_a_received_on);
        $this->assertNull($milestone->polio_received_on);
    }

    #[Test]
    public function existing_milestone_dates_are_not_overwritten_by_a_later_report()
    {
        StudentHealthMilestone::create([
            'user_id' => $this->studentUser->id,
            'hep_a_received_on' => '2024-01-15',
        ]);

        $this->actingAs($this->caregiver)->post(route('health.store'), [
            'student' => $this->studentUser->id,
            'general-condition' => 'Healthy',
            'hep-a-vax' => '1',
        ]);

        $this->assertSame(
            '2024-01-15',
            StudentHealthMilestone::where('user_id', $this->studentUser->id)->first()->hep_a_received_on->toDateString(),
            'A repeated yes for an already-recorded milestone must not overwrite the original date.'
        );
    }

    #[Test]
    public function answering_no_or_blank_does_not_create_a_milestone_date()
    {
        $this->actingAs($this->caregiver)->post(route('health.store'), [
            'student' => $this->studentUser->id,
            'general-condition' => 'Healthy',
            'hep-a-vax' => '0',
            // polio-vax not provided at all
        ]);

        $milestone = StudentHealthMilestone::where('user_id', $this->studentUser->id)->first();
        // The row exists (firstOrNew + save) but no dates set.
        $this->assertNotNull($milestone);
        $this->assertNull($milestone->hep_a_received_on);
        $this->assertNull($milestone->polio_received_on);
    }

    #[Test]
    public function create_form_omits_milestone_questions_already_recorded()
    {
        StudentHealthMilestone::create([
            'user_id' => $this->studentUser->id,
            'hep_a_received_on' => '2024-01-15',
            'polio_received_on' => '2024-01-15',
        ]);

        $response = $this->actingAs($this->caregiver)->get(route('health.create', $this->studentUser));
        $response->assertOk();
        $html = $response->getContent();

        // Recorded ones: shown as text, no input.
        $this->assertStringContainsString('Hep A vaccine recorded on', $html);
        $this->assertStringNotContainsString('name="hep-a-vax"', $html);

        // Not yet recorded: still shown as a select.
        $this->assertStringContainsString('name="tetanus-vax"', $html);
        $this->assertStringContainsString('name="yellow-fever-vax"', $html);
    }
}
