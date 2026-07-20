<?php

namespace Tests\Feature;

use App\Livewire\Termreports;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TermreportsDefaultTermTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_report_screen_defaults_to_the_term_containing_today(): void
    {
        // TestCase ships Term 1 (Sep-Dec). Add the rest of the year and stand
        // inside Term 2: the selector must land there, not on Term 1.
        $term2 = Term::create(['name' => 'Term 2', 'start' => '2025-01-01', 'end' => '2025-04-30', 'schoolyear_id' => $this->schoolyear->id]);
        Term::create(['name' => 'Term 3', 'start' => '2025-05-01', 'end' => '2025-08-31', 'schoolyear_id' => $this->schoolyear->id]);
        Carbon::setTestNow('2025-02-10');

        Livewire::actingAs($this->headmaster)
            ->test(Termreports::class)
            ->assertSet('selectedTerm', $term2->id);

        Carbon::setTestNow();
    }

    #[Test]
    public function between_terms_it_falls_back_to_the_latest_started_term(): void
    {
        $term2 = Term::create(['name' => 'Term 2', 'start' => '2025-01-01', 'end' => '2025-04-30', 'schoolyear_id' => $this->schoolyear->id]);
        Carbon::setTestNow('2025-06-15'); // after Term 2 ended, no term contains today

        Livewire::actingAs($this->headmaster)
            ->test(Termreports::class)
            ->assertSet('selectedTerm', $term2->id);

        Carbon::setTestNow();
    }
}
