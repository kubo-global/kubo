<?php

namespace Tests\Feature;

use App\Models\GradingScale;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GradingScaleTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    public function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create();

        GradingScale::factory()->create(['school_id' => $this->school->id, 'label' => 'A', 'min_score' => 70, 'max_score' => 100, 'remark' => 'Excellent', 'display_order' => 1]);
        GradingScale::factory()->create(['school_id' => $this->school->id, 'label' => 'B', 'min_score' => 60, 'max_score' => 69.99, 'remark' => 'Very Good', 'display_order' => 2]);
        GradingScale::factory()->create(['school_id' => $this->school->id, 'label' => 'C', 'min_score' => 50, 'max_score' => 59.99, 'remark' => 'Good', 'display_order' => 3]);
        GradingScale::factory()->create(['school_id' => $this->school->id, 'label' => 'F', 'min_score' => 0, 'max_score' => 49.99, 'remark' => 'Fail', 'display_order' => 4]);
    }

    #[Test]
    public function it_resolves_the_correct_grade_for_a_score()
    {
        $grade = GradingScale::resolve($this->school, 85);
        $this->assertEquals('A', $grade->label);
        $this->assertEquals('Excellent', $grade->remark);

        $grade = GradingScale::resolve($this->school, 55);
        $this->assertEquals('C', $grade->label);
    }

    #[Test]
    public function it_handles_boundary_scores()
    {
        $grade = GradingScale::resolve($this->school, 70);
        $this->assertEquals('A', $grade->label);

        $grade = GradingScale::resolve($this->school, 69.99);
        $this->assertEquals('B', $grade->label);

        $grade = GradingScale::resolve($this->school, 0);
        $this->assertEquals('F', $grade->label);

        $grade = GradingScale::resolve($this->school, 100);
        $this->assertEquals('A', $grade->label);
    }

    #[Test]
    public function it_returns_null_when_no_scale_matches()
    {
        $grade = GradingScale::resolve($this->school, 150);
        $this->assertNull($grade);
    }

    #[Test]
    public function it_resolves_grades_for_the_correct_school()
    {
        $otherSchool = School::factory()->create();
        GradingScale::factory()->create(['school_id' => $otherSchool->id, 'label' => 'Pass', 'min_score' => 50, 'max_score' => 100, 'remark' => 'Passed']);

        $grade = GradingScale::resolve($otherSchool, 85);
        $this->assertEquals('Pass', $grade->label);

        $grade = GradingScale::resolve($this->school, 85);
        $this->assertEquals('A', $grade->label);
    }
}
