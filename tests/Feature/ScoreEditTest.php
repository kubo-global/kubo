<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AssessmentType;
use App\Models\Grade;
use App\Models\Offering;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression: correcting an already-entered score must persist. The scores table
 * has a composite key (user_id, assessment_id) and no `id`; before the model was
 * taught this, updateOrCreate's UPDATE path keyed on a non-existent `id` — a 500
 * on MySQL, a silent no-op on SQLite (so the old suite never caught it).
 */
class ScoreEditTest extends TestCase
{
    use RefreshDatabase;

    private function assessmentWithPupil(): array
    {
        $school = School::create(['name' => 'S']);
        $type = AssessmentType::create(['school_id' => $school->id, 'name' => 'Test', 'weight' => 0.25, 'display_order' => 1]);
        $offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id'      => Grade::factory()->create()->id,
        ]);
        $assessment = Assessment::factory()->create([
            'assessment_type_id' => $type->id,
            'offering_id'        => $offering->id,
            'subject_id'         => Subject::create(['name' => 'Mathematics'])->id,
            'term_id'            => $this->term->id,
            'max_score'          => 100,
        ]);
        $pupil = Student::factory()->create();

        return [$assessment, $pupil];
    }

    private function scoreFor(Assessment $a, Student $p): ?int
    {
        $v = AssessmentScore::where('assessment_id', $a->id)->where('user_id', $p->id)->value('score');

        return $v === null ? null : (int) $v;
    }

    #[Test]
    public function a_previously_entered_score_can_be_corrected(): void
    {
        [$assessment, $pupil] = $this->assessmentWithPupil();

        // First entry.
        $this->actingAs($this->headmaster)
            ->post(route('reporting.assessment.saveScores', $assessment), ['scores' => [$pupil->id => 40]])
            ->assertRedirect();
        $this->assertSame(40, $this->scoreFor($assessment, $pupil));

        // Correction — the path that used to fail.
        $this->actingAs($this->headmaster)
            ->post(route('reporting.assessment.saveScores', $assessment), ['scores' => [$pupil->id => 85]])
            ->assertRedirect();

        $this->assertSame(85, $this->scoreFor($assessment, $pupil), 'The corrected score should be saved.');
        $this->assertSame(
            1,
            AssessmentScore::where('assessment_id', $assessment->id)->where('user_id', $pupil->id)->count(),
            'Correcting a score must update the row, not duplicate it.'
        );
    }

    #[Test]
    public function a_scored_pupil_can_later_be_marked_absent(): void
    {
        [$assessment, $pupil] = $this->assessmentWithPupil();

        $this->actingAs($this->headmaster)
            ->post(route('reporting.assessment.saveScores', $assessment), ['scores' => [$pupil->id => 55]])
            ->assertRedirect();

        // Switch the same pupil from a score to absent (score null, absent true).
        $this->actingAs($this->headmaster)
            ->post(route('reporting.assessment.saveScores', $assessment), [
                'scores' => [$pupil->id => ''],
                'absent' => [$pupil->id => 1],
            ])->assertRedirect();

        $row = AssessmentScore::where('assessment_id', $assessment->id)->where('user_id', $pupil->id)->firstOrFail();
        $this->assertNull($row->score);
        $this->assertTrue((bool) $row->absent);
    }
}
