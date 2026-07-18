<?php

namespace Tests\Feature;

use App\Domain\Reporting\Repositories\NewTermReportRepository;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AssessmentType;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Offering;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PositionsTest extends TestCase
{
    use RefreshDatabase;

    /** A class with two subjects (Maths counts, Arts toggleable), one test type, and two scored pupils. */
    private function classWithScores(): array
    {
        $school = School::factory()->create();
        $offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id'      => Grade::factory()->create()->id,
        ]);
        $maths = Subject::factory()->create(['name' => 'Mathematics', 'counts_toward_total' => true]);
        $arts  = Subject::factory()->create(['name' => 'Arts', 'counts_toward_total' => true]);
        $offering->subjects($this->term->id)->save($maths, ['term_id' => $this->term->id]);
        $offering->subjects($this->term->id)->save($arts, ['term_id' => $this->term->id]);

        $type = AssessmentType::factory()->test()->create(['school_id' => $school->id]);
        $mathsA = Assessment::factory()->create(['assessment_type_id' => $type->id, 'offering_id' => $offering->id, 'term_id' => $this->term->id, 'subject_id' => $maths->id, 'name' => 'Test 1']);
        $artsA  = Assessment::factory()->create(['assessment_type_id' => $type->id, 'offering_id' => $offering->id, 'term_id' => $this->term->id, 'subject_id' => $arts->id, 'name' => 'Test 1']);

        $top = Student::factory()->create(['first_name' => 'Top', 'last_name' => 'Pupil']);
        $low = Student::factory()->create(['first_name' => 'Low', 'last_name' => 'Pupil']);
        Enrollment::factory()->create(['user_id' => $top->id, 'offering_id' => $offering->id]);
        Enrollment::factory()->create(['user_id' => $low->id, 'offering_id' => $offering->id]);

        AssessmentScore::factory()->create(['user_id' => $top->id, 'assessment_id' => $mathsA->id, 'score' => 9]);
        AssessmentScore::factory()->create(['user_id' => $low->id, 'assessment_id' => $mathsA->id, 'score' => 3]);
        AssessmentScore::factory()->create(['user_id' => $top->id, 'assessment_id' => $artsA->id, 'score' => 5]);
        AssessmentScore::factory()->create(['user_id' => $low->id, 'assessment_id' => $artsA->id, 'score' => 5]);

        $topEnrollment = Enrollment::where('user_id', $top->id)->where('offering_id', $offering->id)->first();

        return compact('offering', 'school', 'topEnrollment', 'arts');
    }

    #[Test]
    public function a_subject_can_be_toggled_to_not_count_toward_total(): void
    {
        $subject = Subject::factory()->create(['counts_toward_total' => true]);

        $this->actingAs($this->headmaster)
            ->post(route('settings.toggle-subject-total', $subject)) // no 'counts' => unchecked
            ->assertRedirect();
        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'counts_toward_total' => false]);

        $this->actingAs($this->headmaster)
            ->post(route('settings.toggle-subject-total', $subject), ['counts' => '1'])
            ->assertRedirect();
        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'counts_toward_total' => true]);
    }

    #[Test]
    public function the_positions_page_ranks_pupils_by_total(): void
    {
        ['offering' => $offering] = $this->classWithScores();

        $this->actingAs($this->headmaster)
            ->get(route('scorebook.positions', ['offering' => $offering, 'term' => $this->term->id]))
            ->assertOk()
            ->assertSee('Positions')
            ->assertSeeInOrder(['Top Pupil', 'Low Pupil']); // higher total listed first
    }

    #[Test]
    public function a_non_counting_subject_is_excluded_from_the_total(): void
    {
        ['school' => $school, 'topEnrollment' => $enrollment, 'arts' => $arts] = $this->classWithScores();

        $arts->update(['counts_toward_total' => false]);
        $without = (new NewTermReportRepository($enrollment, $this->term, $school))->getReportData()['results']['total'];

        $arts->update(['counts_toward_total' => true]);
        $with = (new NewTermReportRepository($enrollment, $this->term, $school))->getReportData()['results']['total'];

        $this->assertGreaterThan($without, $with, 'Counting Arts should raise the total.');
    }

    #[Test]
    public function a_per_class_term_override_excludes_a_subject_from_the_total(): void
    {
        ['offering' => $offering, 'school' => $school, 'topEnrollment' => $enrollment, 'arts' => $arts] = $this->classWithScores();

        // Baseline: Arts counts school-wide, so it is part of the total.
        $with = (new NewTermReportRepository($enrollment, $this->term, $school))->getReportData()['results']['total'];

        // Override for THIS class + term only: Arts stops counting. The global flag is left alone.
        $this->actingAs($this->headmaster)
            ->post(route('settings.set-class-subject-counting'), [
                'subject_id'  => $arts->id,
                'term_id'     => $this->term->id,
                'offering_id' => $offering->id,
                // no 'counts' key => unchecked => false
            ])->assertRedirect();

        $this->assertDatabaseHas('subject_term_offering', [
            'subject_id' => $arts->id, 'term_id' => $this->term->id,
            'offering_id' => $offering->id, 'counts_toward_total' => false,
        ]);
        $this->assertDatabaseHas('subjects', ['id' => $arts->id, 'counts_toward_total' => true]); // school-wide default untouched

        $without = (new NewTermReportRepository($enrollment, $this->term, $school))->getReportData()['results']['total'];
        $this->assertGreaterThan($without, $with, 'The per-class/term override should drop Arts from the total.');

        // Re-including it through the same route restores the total exactly.
        $this->actingAs($this->headmaster)
            ->post(route('settings.set-class-subject-counting'), [
                'subject_id'  => $arts->id,
                'term_id'     => $this->term->id,
                'offering_id' => $offering->id,
                'counts'      => '1',
            ])->assertRedirect();

        $restored = (new NewTermReportRepository($enrollment, $this->term, $school))->getReportData()['results']['total'];
        $this->assertEquals($with, $restored);
    }

    #[Test]
    public function a_plain_teacher_cannot_override_per_class_term_counting(): void
    {
        ['offering' => $offering, 'arts' => $arts] = $this->classWithScores();

        $this->actingAs($this->teacher)
            ->post(route('settings.set-class-subject-counting'), [
                'subject_id'  => $arts->id,
                'term_id'     => $this->term->id,
                'offering_id' => $offering->id,
                'counts'      => '1',
            ])->assertForbidden();
    }

    #[Test]
    public function a_plain_teacher_cannot_toggle_subject_totals(): void
    {
        $subject = Subject::factory()->create();

        $this->actingAs($this->teacher)
            ->post(route('settings.toggle-subject-total', $subject), ['counts' => '1'])
            ->assertForbidden();
    }

    #[Test]
    public function the_result_sheet_downloads_a_pdf(): void
    {
        ['offering' => $offering] = $this->classWithScores();

        $response = $this->actingAs($this->headmaster)
            ->get(route('scorebook.result-sheet', ['offering' => $offering, 'term' => $this->term->id]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }
}
