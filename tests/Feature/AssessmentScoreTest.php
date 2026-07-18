<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AssessmentType;
use App\Models\School;
use App\Exceptions\AssessmentScoreHigherThanMaxScore;
use App\Exceptions\AssessmentScoreIsNullAndNotAbsent;
use App\Exceptions\AssessmentScoreIsNotNullAndAbsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssessmentScoreTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    private $assessment;

    public function setUp(): void
    {
        parent::setUp();

        $school = School::factory()->create();
        $type = AssessmentType::factory()->test()->create(['school_id' => $school->id]);
        $this->assessment = Assessment::factory()->create([
            'assessment_type_id' => $type->id,
            'max_score' => 100,
        ]);
    }

    #[Test]
    public function it_can_not_save_a_score_higher_than_the_assessment_max_score()
    {
        $this->withoutExceptionHandling();
        $this->expectException(AssessmentScoreHigherThanMaxScore::class);

        $assessment = Assessment::factory()->create([
            'assessment_type_id' => $this->assessment->assessment_type_id,
            'max_score' => 10,
        ]);
        AssessmentScore::factory()->create([
            'assessment_id' => $assessment->id,
            'score' => 11,
        ]);
    }

    #[Test]
    public function it_can_save_a_score_less_or_equal_than_the_assessment_max_score()
    {
        $maxScore = 10;
        $assessment = Assessment::factory()->create([
            'assessment_type_id' => $this->assessment->assessment_type_id,
            'max_score' => $maxScore,
        ]);

        $score = AssessmentScore::factory()->create([
            'assessment_id' => $assessment->id,
            'score' => $maxScore,
        ]);

        $this->assertEquals($score->score, 10);

        $score = AssessmentScore::factory()->create([
            'assessment_id' => $assessment->id,
            'score' => $maxScore - 1,
        ]);

        $this->assertEquals($score->score, $maxScore - 1);
    }

    #[Test]
    public function it_can_save_a_non_null_score_while_not_absent()
    {
        $this->assertEquals(0, AssessmentScore::count());

        AssessmentScore::factory()->create([
            'assessment_id' => $this->assessment->id,
            'score' => $this->assessment->max_score,
            'absent' => false,
        ]);

        $this->assertEquals(1, AssessmentScore::count());
    }

    #[Test]
    public function it_can_save_a_null_score_while_absent()
    {
        $this->assertEquals(0, AssessmentScore::count());

        AssessmentScore::factory()->create([
            'assessment_id' => $this->assessment->id,
            'score' => null,
            'absent' => true,
        ]);

        $this->assertEquals(1, AssessmentScore::count());
    }

    #[Test]
    public function it_can_not_save_a_non_null_score_while_absent()
    {
        $this->withoutExceptionHandling();
        $this->expectException(AssessmentScoreIsNotNullAndAbsent::class);

        AssessmentScore::factory()->create([
            'assessment_id' => $this->assessment->id,
            'score' => $this->assessment->max_score,
            'absent' => true,
        ]);
    }

    #[Test]
    public function it_can_not_save_a_null_score_while_not_absent()
    {
        $this->withoutExceptionHandling();
        $this->expectException(AssessmentScoreIsNullAndNotAbsent::class);

        AssessmentScore::factory()->create([
            'assessment_id' => $this->assessment->id,
            'score' => null,
            'absent' => false,
        ]);
    }
}
