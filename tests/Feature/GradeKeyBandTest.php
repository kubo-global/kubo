<?php

namespace Tests\Feature;

use App\Models\GradingScale;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Per-grade-band grade keys and the guard against ambiguous bands.
 */
class GradeKeyBandTest extends TestCase
{
    use RefreshDatabase;

    private function freshKey(): School
    {
        $school = School::first() ?? School::factory()->create();
        $school->gradingScales()->whereNull('purpose')->delete();

        return $school;
    }

    #[Test]
    public function resolve_picks_the_band_set_for_the_pupils_grade(): void
    {
        $school = $this->freshKey();
        foreach ([[1, 3, 'B', 70, 84], [1, 3, 'C', 60, 69], [4, 6, 'B', 75, 84], [4, 6, 'C', 60, 74]] as [$gmin, $gmax, $label, $min, $max]) {
            GradingScale::create(['school_id' => $school->id, 'purpose' => null, 'grade_min' => $gmin, 'grade_max' => $gmax, 'label' => $label, 'min_score' => $min, 'max_score' => $max]);
        }

        // 72 is a B in Grade 1-3 (70-84) but a C in Grade 4-6 (60-74).
        $this->assertSame('B', GradingScale::resolve($school, 72, 3)?->label);
        $this->assertSame('C', GradingScale::resolve($school, 72, 5)?->label);
    }

    #[Test]
    public function a_null_range_band_is_the_all_grades_fallback(): void
    {
        $school = $this->freshKey();
        GradingScale::create(['school_id' => $school->id, 'purpose' => null, 'grade_min' => null, 'grade_max' => null, 'label' => 'P', 'min_score' => 40, 'max_score' => 100]);

        // No grade-specific band, so any grade uses the default set.
        $this->assertSame('P', GradingScale::resolve($school, 55, 3)?->label);
        $this->assertSame('P', GradingScale::resolve($school, 55, null)?->label);
    }

    #[Test]
    public function overlapping_grade_ranges_are_rejected(): void
    {
        $school = $this->freshKey();
        GradingScale::create(['school_id' => $school->id, 'purpose' => null, 'grade_min' => 1, 'grade_max' => 3, 'label' => 'A', 'min_score' => 85, 'max_score' => 100]);
        $before = GradingScale::whereNull('purpose')->count();

        $this->actingAs($this->headmaster)
            ->post(route('settings.store-grade-band'), ['label' => 'X', 'min_score' => 85, 'max_score' => 100, 'grade_min' => 2, 'grade_max' => 5])
            ->assertRedirect();

        $this->assertSame($before, GradingScale::whereNull('purpose')->count()); // blocked
        $this->assertNotNull(session('error'));
    }

    #[Test]
    public function overlapping_score_bands_in_the_same_grade_range_are_rejected(): void
    {
        $school = $this->freshKey();
        GradingScale::create(['school_id' => $school->id, 'purpose' => null, 'grade_min' => 1, 'grade_max' => 3, 'label' => 'B', 'min_score' => 70, 'max_score' => 84]);
        $before = GradingScale::whereNull('purpose')->count();

        $this->actingAs($this->headmaster)
            ->post(route('settings.store-grade-band'), ['label' => 'X', 'min_score' => 75, 'max_score' => 90, 'grade_min' => 1, 'grade_max' => 3])
            ->assertRedirect();

        $this->assertSame($before, GradingScale::whereNull('purpose')->count()); // blocked
    }

    #[Test]
    public function a_non_overlapping_band_is_accepted(): void
    {
        $this->freshKey();
        $before = GradingScale::whereNull('purpose')->count();

        $this->actingAs($this->headmaster)
            ->post(route('settings.store-grade-band'), ['label' => 'A', 'min_score' => 85, 'max_score' => 100, 'grade_min' => 1, 'grade_max' => 3])
            ->assertRedirect();

        $this->assertSame($before + 1, GradingScale::whereNull('purpose')->count());
    }
}
