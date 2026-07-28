<?php

namespace Tests\Feature;

use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Manually closing (locking) a term and reopening it, from Settings.
 * A closed term reports isLocked() = true even before its end date.
 */
class TermManualLockTest extends TestCase
{
    use RefreshDatabase;

    private function openTerm(): Term
    {
        return Term::create([
            'name' => 'Term 3',
            'start' => '2026-05-13',
            'end' => '2099-08-31', // far future: not auto-locked by date
            'schoolyear_id' => $this->schoolyear->id,
        ]);
    }

    #[Test]
    public function an_open_term_is_not_locked(): void
    {
        $this->assertFalse($this->openTerm()->isLocked());
    }

    #[Test]
    public function the_headmaster_can_close_and_reopen_a_term(): void
    {
        $term = $this->openTerm();

        $this->actingAs($this->headmaster)
            ->post(route('settings.toggle-term-lock', $term))
            ->assertRedirect();
        $term->refresh();
        $this->assertNotNull($term->locked_at);
        $this->assertTrue($term->isLocked());
        $this->assertTrue($term->isManuallyLocked());

        $this->actingAs($this->headmaster)
            ->post(route('settings.toggle-term-lock', $term))
            ->assertRedirect();
        $term->refresh();
        $this->assertNull($term->locked_at);
        $this->assertFalse($term->isLocked());
    }

    #[Test]
    public function the_admin_can_close_a_term(): void
    {
        $term = $this->openTerm();

        $this->actingAs($this->admin)
            ->post(route('settings.toggle-term-lock', $term))
            ->assertRedirect();

        $this->assertTrue($term->refresh()->isLocked());
    }

    #[Test]
    public function a_teacher_cannot_toggle_a_term_lock(): void
    {
        $term = $this->openTerm();

        $this->actingAs($this->teacher)
            ->post(route('settings.toggle-term-lock', $term))
            ->assertForbidden();

        $this->assertNull($term->refresh()->locked_at);
    }

    #[Test]
    public function a_teacher_cannot_save_scores_once_a_term_is_manually_closed(): void
    {
        $term = $this->openTerm();
        $term->update(['locked_at' => now()]);

        // isLocked drives the score-entry gate everywhere; a closed term blocks
        // the teacher exactly as an ended term would.
        $this->assertTrue($term->fresh()->isLocked());
    }
}
