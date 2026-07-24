<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Grade;
use App\Models\Offering;
use App\Models\School;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests-mode schools create assessments in fixed Test 1 / Test 2 / Exam slots:
 * the name is one of the slots, the date lands in the slot's bucket month, and
 * a subject can hold each slot only once per term.
 */
class AssessmentSlotTest extends TestCase
{
    use RefreshDatabase;

    private function testsModeClass(): array
    {
        $school = School::first() ?? School::factory()->create();
        $school->configs()->updateOrCreate(['key' => 'scorebook_period_mode'], ['value' => 'tests']);
        $test = AssessmentType::factory()->test()->create(['school_id' => $school->id]);
        $exam = AssessmentType::factory()->exam()->create(['school_id' => $school->id]);

        $offering = Offering::factory()->create(['schoolyear_id' => $this->schoolyear->id, 'grade_id' => Grade::factory()->create(['name' => 'Grade 2'])->id]);
        $maths = Subject::factory()->create(['name' => 'Mathematics', 'counts_toward_total' => true]);
        $offering->subjects($this->term->id)->save($maths, ['term_id' => $this->term->id]);

        return compact('school', 'test', 'exam', 'offering', 'maths');
    }

    private function storePayload(array $c, array $overrides = []): array
    {
        return array_merge([
            'assessment_type_id' => $c['test']->id,
            'offering_id' => $c['offering']->id,
            'term_id' => $this->term->id,
            'subject_id' => $c['maths']->id,
            'name' => 'Test 1',
            'max_score' => 25,
        ], $overrides);
    }

    #[Test]
    public function a_free_typed_name_is_rejected_in_tests_mode(): void
    {
        $c = $this->testsModeClass();

        $this->actingAs($this->headmaster)
            ->post(route('reporting.assessment.store'), $this->storePayload($c, ['name' => 'my own test']))
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Assessment::where('subject_id', $c['maths']->id)->count());
    }

    #[Test]
    public function a_slot_gets_its_bucket_month_regardless_of_the_submitted_date(): void
    {
        $c = $this->testsModeClass();

        $this->actingAs($this->headmaster)
            ->post(route('reporting.assessment.store'), $this->storePayload($c, ['date' => '2030-12-25']));

        $a = Assessment::where('subject_id', $c['maths']->id)->firstOrFail();
        $expected = $this->term->testsBucketMonths()['Test 1']->format('Y-m');
        $this->assertSame($expected, \Illuminate\Support\Carbon::parse($a->date)->format('Y-m'));
    }

    #[Test]
    public function the_same_slot_cannot_be_created_twice_for_a_subject_in_a_term(): void
    {
        $c = $this->testsModeClass();

        $this->actingAs($this->headmaster)->post(route('reporting.assessment.store'), $this->storePayload($c));
        $this->actingAs($this->headmaster)
            ->post(route('reporting.assessment.store'), $this->storePayload($c))
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Assessment::where('subject_id', $c['maths']->id)->count());
    }

    #[Test]
    public function an_exam_type_only_accepts_the_exam_slot(): void
    {
        $c = $this->testsModeClass();

        $this->actingAs($this->headmaster)
            ->post(route('reporting.assessment.store'), $this->storePayload($c, ['assessment_type_id' => $c['exam']->id, 'name' => 'Test 1', 'max_score' => 75]))
            ->assertSessionHasErrors('name');

        $this->actingAs($this->headmaster)
            ->post(route('reporting.assessment.store'), $this->storePayload($c, ['assessment_type_id' => $c['exam']->id, 'name' => 'Exam', 'max_score' => 75]));

        $this->assertSame('Exam', Assessment::where('subject_id', $c['maths']->id)->firstOrFail()->name);
    }

    #[Test]
    public function months_mode_schools_keep_free_naming(): void
    {
        $c = $this->testsModeClass();
        $c['school']->configs()->updateOrCreate(['key' => 'scorebook_period_mode'], ['value' => 'months']);

        $this->actingAs($this->headmaster)
            ->post(route('reporting.assessment.store'), $this->storePayload($c, ['name' => 'March test']))
            ->assertSessionDoesntHaveErrors('name');

        $this->assertSame('March test', Assessment::where('subject_id', $c['maths']->id)->firstOrFail()->name);
    }
}
