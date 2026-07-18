<?php

namespace Tests\Feature;

use App\Domain\Reporting\Repositories\NewTermReportRepository;
use App\Domain\Reporting\Services\ReportGeneratorService;
use App\Livewire\PrepareReports;
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
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrepareReportsTest extends TestCase
{
    use RefreshDatabase;

    /** A class with two pupils, each with a Test + Exam so they rank. */
    private function scoredClass(): array
    {
        $school = School::first() ?? School::factory()->create();
        $test = AssessmentType::factory()->test()->create(['school_id' => $school->id]);
        $exam = AssessmentType::factory()->exam()->create(['school_id' => $school->id]);

        $offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id' => Grade::factory()->create(['name' => 'Grade 5'])->id,
        ]);
        $maths = Subject::factory()->create(['name' => 'Mathematics', 'counts_toward_total' => true]);
        $offering->subjects($this->term->id)->save($maths, ['term_id' => $this->term->id]);

        $testA = Assessment::factory()->create(['assessment_type_id' => $test->id, 'offering_id' => $offering->id, 'term_id' => $this->term->id, 'subject_id' => $maths->id, 'max_score' => 100]);
        $examA = Assessment::factory()->create(['assessment_type_id' => $exam->id, 'offering_id' => $offering->id, 'term_id' => $this->term->id, 'subject_id' => $maths->id, 'max_score' => 100]);

        $top = Student::factory()->create(['first_name' => 'Top', 'last_name' => 'Pupil']);
        $low = Student::factory()->create(['first_name' => 'Low', 'last_name' => 'Pupil']);
        $topEnr = Enrollment::factory()->create(['user_id' => $top->id, 'offering_id' => $offering->id]);
        Enrollment::factory()->create(['user_id' => $low->id, 'offering_id' => $offering->id]);

        AssessmentScore::factory()->create(['user_id' => $top->id, 'assessment_id' => $testA->id, 'score' => 90]);
        AssessmentScore::factory()->create(['user_id' => $top->id, 'assessment_id' => $examA->id, 'score' => 90]);
        AssessmentScore::factory()->create(['user_id' => $low->id, 'assessment_id' => $testA->id, 'score' => 40]);
        AssessmentScore::factory()->create(['user_id' => $low->id, 'assessment_id' => $examA->id, 'score' => 40]);

        return compact('school', 'offering', 'top', 'topEnr');
    }

    #[Test]
    public function it_lists_pupils_in_rank_order(): void
    {
        ['offering' => $offering] = $this->scoredClass();

        Livewire::actingAs($this->headmaster)
            ->test(PrepareReports::class, ['offering' => $offering])
            ->assertSet('termId', $this->term->id)
            ->assertSeeInOrder(['Top Pupil', 'Low Pupil']); // higher term total first
    }

    #[Test]
    public function typing_a_remark_saves_it_and_it_prints_on_the_card(): void
    {
        ['school' => $school, 'offering' => $offering, 'top' => $top, 'topEnr' => $topEnr] = $this->scoredClass();

        Livewire::actingAs($this->headmaster)
            ->test(PrepareReports::class, ['offering' => $offering])
            ->set('conduct.'.$topEnr->id, 'Good')
            ->set('remarks.'.$topEnr->id, 'A self-disciplined child')
            ->call('saveRemark', $topEnr->id)
            ->assertSet('savedFor', $topEnr->id);

        $this->assertDatabaseHas('report_remarks', [
            'enrollment_id' => $topEnr->id,
            'term_id' => $this->term->id,
            'conduct' => 'Good',
            'general_remarks' => 'A self-disciplined child',
        ]);

        // The saved feedback, read back from the DB and keyed by pupil (as the
        // controller does), prints on the Swallow card.
        $saved = \App\Models\ReportRemark::where('enrollment_id', $topEnr->id)->where('term_id', $this->term->id)->firstOrFail();
        $remarks = collect([$top->id => ['conduct' => $saved->conduct, 'general' => $saved->general_remarks]]);

        $reports = (new ReportGeneratorService())->generateStudentReportPdf(
            new NewTermReportRepository($topEnr->fresh(), $this->term, $school),
        );
        $html = view('print.termReport-swallow', ['reports' => $reports, 'positions' => collect(), 'classSize' => 1, 'remarks' => $remarks])->render();

        $this->assertStringContainsString('A self-disciplined child', $html);
        $this->assertStringContainsString('Conduct:</strong> Good', $html);
    }

    #[Test]
    public function it_refuses_a_remark_for_a_pupil_of_another_class(): void
    {
        ['offering' => $offering] = $this->scoredClass();

        // A pupil enrolled in a different class; its enrollment id must be rejected.
        $other = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id' => Grade::factory()->create(['name' => 'Grade 6'])->id,
        ]);
        $foreign = Enrollment::factory()->create([
            'user_id' => Student::factory()->create()->id,
            'offering_id' => $other->id,
        ]);

        Livewire::actingAs($this->headmaster)
            ->test(PrepareReports::class, ['offering' => $offering])
            ->set('remarks.'.$foreign->id, 'should not land')
            ->call('saveRemark', $foreign->id)
            ->assertStatus(403);

        $this->assertDatabaseMissing('report_remarks', ['enrollment_id' => $foreign->id]);
    }

    #[Test]
    public function the_prepare_screen_is_reachable_by_staff(): void
    {
        ['offering' => $offering] = $this->scoredClass();

        $this->actingAs($this->headmaster)
            ->get(route('term-report.prepare', $offering))
            ->assertOk()
            ->assertSee('Prepare reports');
    }

    #[Test]
    public function the_report_overview_opens_the_prepare_screen(): void
    {
        ['offering' => $offering] = $this->scoredClass();

        Livewire::actingAs($this->headmaster)
            ->test(\App\Livewire\Termreports::class)
            ->set('selectedSchoolyear', $this->schoolyear->id)
            ->set('selectedGrade', $offering->grade_id)
            ->set('selectedTerm', $this->term->id)
            ->call('prepareReports')
            ->assertRedirect(route('term-report.prepare', ['offering' => $offering->id, 'term' => $this->term->id]));
    }
}
