<?php

namespace Tests\Feature;

use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The scorebook period mode and term-card layout are plain Settings now, so a
 * headmaster can fix them from the browser — no server access needed (learned
 * the hard way when The Swallow sat on 'months' with the tunnel down).
 */
class SettingsScorebookConfigTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_headmaster_switches_the_period_mode_from_settings(): void
    {
        $school = School::factory()->create();

        $this->actingAs($this->headmaster)
            ->post(route('settings.update-period-mode'), ['period_mode' => 'tests'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('tests', $school->fresh()->config('scorebook_period_mode'));

        $this->actingAs($this->headmaster)
            ->post(route('settings.update-period-mode'), ['period_mode' => 'weeks'])
            ->assertSessionHasErrors('period_mode');
    }

    #[Test]
    public function a_headmaster_switches_the_term_card_layout_from_settings(): void
    {
        $school = School::factory()->create();

        $this->actingAs($this->headmaster)
            ->post(route('settings.update-term-card-layout'), ['term_card_layout' => 'swallow'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('swallow', $school->fresh()->config('term_card_layout'));

        $this->actingAs($this->headmaster)
            ->post(route('settings.update-term-card-layout'), ['term_card_layout' => 'bogus'])
            ->assertSessionHasErrors('term_card_layout');
    }

    #[Test]
    public function teachers_cannot_change_these_settings(): void
    {
        School::factory()->create();

        $this->actingAs($this->teacher)
            ->post(route('settings.update-period-mode'), ['period_mode' => 'tests'])
            ->assertForbidden();
    }

    #[Test]
    public function the_settings_page_shows_both_selectors(): void
    {
        School::factory()->create();

        $this->actingAs($this->headmaster)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Score entry periods')
            ->assertSee('Term card layout');
    }
}
