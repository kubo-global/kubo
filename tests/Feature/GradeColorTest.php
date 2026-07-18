<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\Offering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GradeColorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_grade_colour_can_be_set_and_reset_from_settings(): void
    {
        $grade = Grade::factory()->create(['name' => 'Grade 3']);

        $this->actingAs($this->headmaster)
            ->post(route('settings.grade-color', $grade), ['color' => 'green'])
            ->assertRedirect();

        $grade->refresh();
        $this->assertSame('green', $grade->color);
        $this->assertSame(Grade::COLORS['green'], $grade->colorTriplet());
        $this->assertStringContainsString('#47b174', $grade->dotStyle()); // brand green swatch

        // reset to automatic
        $this->actingAs($this->headmaster)
            ->post(route('settings.grade-color', $grade), ['color' => ''])
            ->assertRedirect();
        $this->assertNull($grade->fresh()->color);
    }

    #[Test]
    public function an_unknown_colour_is_rejected(): void
    {
        $grade = Grade::factory()->create();

        $this->actingAs($this->headmaster)
            ->post(route('settings.grade-color', $grade), ['color' => 'chartreuse'])
            ->assertSessionHasErrors('color');

        $this->assertNull($grade->fresh()->color);
    }

    #[Test]
    public function an_unset_grade_falls_back_to_a_stable_default_colour(): void
    {
        $grade = Grade::factory()->create(['color' => null]);

        // a valid palette triplet, deterministic by id
        $this->assertContains($grade->colorTriplet(), array_values(Grade::COLORS));
    }

    // --- Per-section shading (classes within a grade) ---

    #[Test]
    public function section_zero_is_the_unchanged_base_style(): void
    {
        $grade = Grade::factory()->create(['color' => 'orange']);
        $this->assertSame($grade->colorStyle(), $grade->sectionColorStyle(0));
    }

    #[Test]
    public function later_sections_get_distinct_shades(): void
    {
        $grade = Grade::factory()->create(['color' => 'orange']);
        $this->assertNotSame($grade->sectionColorStyle(0), $grade->sectionColorStyle(1));
        $this->assertNotSame($grade->sectionColorStyle(1), $grade->sectionColorStyle(2));
    }

    #[Test]
    public function every_palette_colour_and_section_stays_aa_legible(): void
    {
        foreach (array_keys(Grade::COLORS) as $key) {
            $grade = Grade::factory()->create(['color' => $key]);
            for ($i = 0; $i <= 4; $i++) {
                preg_match('/background-color: (#[0-9a-f]{6}); color: (#[0-9a-f]{6})/', $grade->sectionColorStyle($i), $m);
                $ratio = $this->contrast($m[1], $m[2]);
                $this->assertGreaterThanOrEqual(4.5, round($ratio, 2), "Grade colour '{$key}' section {$i} contrast is {$ratio}");
            }
        }
    }

    #[Test]
    public function tag_section_index_numbers_classes_within_a_grade(): void
    {
        $grade = Grade::factory()->create();
        $a = Offering::factory()->create(['grade_id' => $grade->id, 'schoolyear_id' => $this->schoolyear->id, 'name' => 'A']);
        $b = Offering::factory()->create(['grade_id' => $grade->id, 'schoolyear_id' => $this->schoolyear->id, 'name' => 'B']);

        Offering::tagSectionIndex(collect([$b, $a])); // deliberately out of order

        $this->assertSame(0, $a->section_index);
        $this->assertSame(1, $b->section_index);
    }

    private function contrast(string $a, string $b): float
    {
        $lum = function (string $hex): float {
            [$r, $g, $bl] = array_map(function ($c) {
                $c /= 255;
                return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
            }, sscanf($hex, '#%02x%02x%02x'));
            return 0.2126 * $r + 0.7152 * $g + 0.0722 * $bl;
        };
        $la = $lum($a);
        $lb = $lum($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }
}
