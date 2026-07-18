<?php

namespace Tests\Feature;

use App\Models\HealthReport;
use App\Models\StudentHealthMilestone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HealthEditTest extends TestCase
{
    use RefreshDatabase;

    private User $caregiver;
    private User $studentUser;
    private HealthReport $report;

    public function setUp(): void
    {
        parent::setUp();

        $this->caregiver = User::create(['first_name' => 'Care', 'last_name' => 'Giver', 'password' => bcrypt('x')]);
        $this->caregiver->assignRole('caregiver');

        $this->studentUser = User::create(['first_name' => 'Test', 'last_name' => 'Student', 'password' => bcrypt('x')]);
        $this->studentUser->assignRole('student');

        $this->report = HealthReport::create([
            'user_id' => $this->studentUser->id,
            'general_condition' => 'Healthy',
            'height_in_cm' => 130,
            'weight_in_gram' => 28,
            'teeth_condition' => 'Good',
            'worm_treatment_received' => 0,
        ]);
    }

    #[Test]
    public function edit_form_pre_populates_existing_values()
    {
        $response = $this->actingAs($this->caregiver)->get(route('health.edit', $this->report));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('Healthy', $html);
        $this->assertStringContainsString('value="130"', $html);
        $this->assertStringContainsString('Edit health report', $html);
    }

    #[Test]
    public function edit_form_does_not_show_milestone_questions()
    {
        $response = $this->actingAs($this->caregiver)->get(route('health.edit', $this->report));
        $html = $response->getContent();

        $this->assertStringNotContainsString('name="hep-a-vax"', $html);
        $this->assertStringNotContainsString('name="menstruated"', $html);
    }

    #[Test]
    public function update_persists_changes_and_redirects_to_show()
    {
        $response = $this->actingAs($this->caregiver)->put(route('health.update', $this->report), [
            'general-condition' => 'Recovered from cold',
            'height' => 132,
            'weight' => 29,
            'teeth-condition' => 'Excellent',
            'worm-treatment-received' => '1',
        ]);

        $response->assertRedirect(route('health.show', $this->report));

        $this->report->refresh();
        $this->assertSame('Recovered from cold', $this->report->general_condition);
        $this->assertSame(132, $this->report->height_in_cm);
        $this->assertSame('Excellent', $this->report->teeth_condition);
        $this->assertSame(1, $this->report->worm_treatment_received);
    }

    #[Test]
    public function update_does_not_touch_milestones()
    {
        $this->actingAs($this->caregiver)->put(route('health.update', $this->report), [
            'general-condition' => 'Updated',
        ]);

        $this->assertNull(StudentHealthMilestone::where('user_id', $this->studentUser->id)->first());
    }

    #[Test]
    public function nonsense_measurements_are_rejected_instead_of_stored()
    {
        $this->actingAs($this->caregiver)
            ->put(route('health.update', $this->report), [
                'general-condition' => 'Healthy',
                'height' => 1300,              // cm, not mm
                'weight' => 28000,             // kg, not grams
                'teeth-condition' => 'Sparkly', // not one of the options
            ])
            ->assertSessionHasErrors(['height', 'weight', 'teeth-condition']);

        $this->report->refresh();
        $this->assertSame(130, $this->report->height_in_cm);
        $this->assertSame('Good', $this->report->teeth_condition);
    }

    #[Test]
    public function a_weight_in_kilograms_is_stored_as_grams()
    {
        $this->actingAs($this->caregiver)
            ->put(route('health.update', $this->report), [
                'general-condition' => 'Healthy',
                'weight' => 28.5,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(28500, $this->report->fresh()->weight_in_gram);
        $this->assertSame(28.5, $this->report->fresh()->weight_kg);
    }

    #[Test]
    public function plain_teacher_cannot_access_edit_or_update()
    {
        $this->actingAs($this->teacher)
            ->get(route('health.edit', $this->report))
            ->assertForbidden();

        $this->actingAs($this->teacher)
            ->put(route('health.update', $this->report), ['general-condition' => 'x'])
            ->assertForbidden();
    }
}
