<?php

namespace Tests\Feature;

use App\Models\AssessmentType;
use App\Models\GradingScale;
use App\Models\School;
use App\Models\SchoolConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SchoolScopingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function assessment_types_are_scoped_to_their_school()
    {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();

        AssessmentType::factory()->create(['school_id' => $school1->id, 'name' => 'Test']);
        AssessmentType::factory()->create(['school_id' => $school1->id, 'name' => 'Exam']);
        AssessmentType::factory()->create(['school_id' => $school2->id, 'name' => 'Quiz']);

        $this->assertCount(2, $school1->assessmentTypes);
        $this->assertCount(1, $school2->assessmentTypes);
        $this->assertEquals('Quiz', $school2->assessmentTypes->first()->name);
    }

    #[Test]
    public function grading_scales_are_scoped_to_their_school()
    {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();

        GradingScale::factory()->create(['school_id' => $school1->id, 'label' => 'A', 'min_score' => 70, 'max_score' => 100]);
        GradingScale::factory()->create(['school_id' => $school1->id, 'label' => 'B', 'min_score' => 60, 'max_score' => 69]);
        GradingScale::factory()->create(['school_id' => $school2->id, 'label' => 'Pass', 'min_score' => 50, 'max_score' => 100]);

        $this->assertCount(2, $school1->gradingScales);
        $this->assertCount(1, $school2->gradingScales);
    }

    #[Test]
    public function school_configs_are_isolated_between_schools()
    {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();

        SchoolConfig::create(['school_id' => $school1->id, 'key' => 'term_count', 'value' => 3]);
        SchoolConfig::create(['school_id' => $school2->id, 'key' => 'term_count', 'value' => 2]);

        $this->assertEquals(3, $school1->config('term_count'));
        $this->assertEquals(2, $school2->config('term_count'));
    }
}
