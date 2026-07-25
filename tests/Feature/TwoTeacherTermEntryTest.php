<?php

namespace Tests\Feature;

use App\Domain\Reporting\Repositories\NewTermReportRepository;
use App\Domain\Reporting\Services\ReportReadiness;
use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Offering;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Swallow's real report-time flow, end to end: a class teacher enters the
 * whole term in one sitting (switching Test 1 -> Test 2 -> Exam), the French
 * subject teacher logs in separately and enters French for the same periods —
 * in either order. One subject is taught with a single test + exam and must
 * total correctly too. Afterwards the term report is right and the readiness
 * checks stay quiet.
 */
class TwoTeacherTermEntryTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Offering $offering;

    private Term $openTerm;

    private User $classTeacher;

    private User $frenchTeacher;

    private array $subjects = [];

    private array $pupils = [];

    /** period key => 'Y-m' month value, mirroring the grid's Test 1 / Test 2 / Exam buckets. */
    private array $periods = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->school = School::first() ?? School::factory()->create();
        \App\Models\AssessmentType::factory()->test()->create(['school_id' => $this->school->id]);
        \App\Models\AssessmentType::factory()->exam()->create(['school_id' => $this->school->id]);
        // The Swallow runs the scorebook in tests mode (Test 1 / Test 2 / Exam).
        $this->school->configs()->updateOrCreate(['key' => 'scorebook_period_mode'], ['value' => 'tests']);

        $this->offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id' => Grade::factory()->create(['name' => 'Grade 5'])->id,
        ]);

        // An open term spanning >= 3 months so all three period buckets exist.
        $this->openTerm = Term::create([
            'name' => 'Open Term', 'schoolyear_id' => $this->schoolyear->id,
            'start' => now()->subMonths(2)->startOfMonth(), 'end' => now()->addMonth()->endOfMonth(),
        ]);
        $start = \Illuminate\Support\Carbon::parse($this->openTerm->start);
        $this->periods = [
            'test1' => $start->format('Y-m'),
            'test2' => $start->copy()->addMonth()->format('Y-m'),
            'exam' => \Illuminate\Support\Carbon::parse($this->openTerm->end)->format('Y-m'),
        ];

        foreach (['Mathematics', 'English', 'Phonics', 'French'] as $name) {
            $this->subjects[$name] = Subject::factory()->create(['name' => $name]);
            $this->offering->subjects($this->openTerm->id)->save($this->subjects[$name], ['term_id' => $this->openTerm->id]);
        }

        foreach ([1, 2] as $i) {
            $p = Student::factory()->create(['first_name' => "Pupil{$i}"]);
            Enrollment::factory()->create(['user_id' => $p->id, 'offering_id' => $this->offering->id]);
            $this->pupils[] = $p;
        }

        // Class teacher: attached to the class, no subject restriction.
        $this->classTeacher = $this->teacher;
        DB::table('teacher_offering')->insert(['user_id' => $this->classTeacher->id, 'offering_id' => $this->offering->id, 'principal' => 1]);

        // French teacher: only French, like Mamadou at The Swallow.
        $this->frenchTeacher = User::create(['first_name' => 'Mamadou', 'last_name' => 'Coly', 'password' => bcrypt('secret'), 'archived' => false]);
        $this->frenchTeacher->assignRole('teacher');
        DB::table('teacher_offering')->insert(['user_id' => $this->frenchTeacher->id, 'offering_id' => $this->offering->id, 'principal' => 0]);
        DB::table('teacher_assignments')->insert([
            'user_id' => $this->frenchTeacher->id, 'offering_id' => $this->offering->id,
            'subject_id' => $this->subjects['French']->id, 'is_class_teacher' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function saveGrid(User $as, string $period, array $marksBySubjectName): void
    {
        $scores = [];
        foreach ($marksBySubjectName as $name => $byPupil) {
            $scores[$this->subjects[$name]->id] = collect($byPupil)
                ->mapWithKeys(fn ($mark, $i) => [$this->pupils[$i]->id => (string) $mark])->all();
        }

        $this->actingAs($as)->post(route('term-grid.save', $this->offering), [
            'term' => $this->openTerm->id,
            'month' => $this->periods[$period],
            'scores' => $scores,
        ])->assertRedirect()->assertSessionHas('success');
    }

    /** The class teacher's sitting: whole term, switching Test 1 -> Test 2 -> Exam. French left blank. */
    private function classTeacherSitting(): void
    {
        // Phonics is the single-test subject: it only appears in Test 1 and the Exam.
        // Tests are entered out of 25, the exam out of 75 (the grid's tests-mode defaults).
        $this->saveGrid($this->classTeacher, 'test1', [
            'Mathematics' => [21, 15], 'English' => [18, 12], 'Phonics' => [20, 10],
        ]);
        $this->saveGrid($this->classTeacher, 'test2', [
            'Mathematics' => [14, 10], 'English' => [22, 17],
        ]);
        $this->saveGrid($this->classTeacher, 'exam', [
            'Mathematics' => [60, 45], 'English' => [45, 60], 'Phonics' => [45, 60],
        ]);
    }

    /** The French teacher's sitting: same periods, French only. */
    private function frenchTeacherSitting(): void
    {
        $this->saveGrid($this->frenchTeacher, 'test1', ['French' => [22, 12]]);
        $this->saveGrid($this->frenchTeacher, 'test2', ['French' => [18, 7]]);
        $this->saveGrid($this->frenchTeacher, 'exam', ['French' => [60, 30]]);
    }

    private function assertTermIsRight(): void
    {
        $enrollment = Enrollment::where('user_id', $this->pupils[0]->id)->firstOrFail();
        $results = (new NewTermReportRepository($enrollment, $this->openTerm, $this->school))
            ->getReportData()['results'];

        // Pupil 1 — weighted 0.25/0.75, tests /25 and exam /75:
        // Mathematics: tests (84%+56%)/2=70% -> 18; exam 60/75=80% -> 60; total 78.
        // English:     tests (72%+88%)/2=80% -> 20; exam 45/75=60% -> 45; total 65.
        // Phonics:     single test 20/25=80% -> 20; exam 45/75=60% -> 45; total 65.
        // French:      tests (88%+72%)/2=80% -> 20; exam 60/75=80% -> 60; total 80.
        $this->assertSame(78.0, (float) $results['subjectResults']['Mathematics']['subjectTotal']);
        $this->assertSame(65.0, (float) $results['subjectResults']['English']['subjectTotal']);
        $this->assertSame(65.0, (float) $results['subjectResults']['Phonics']['subjectTotal']);
        $this->assertSame(80.0, (float) $results['subjectResults']['French']['subjectTotal']);
        $this->assertSame(288, (int) $results['total']);

        // Exactly the intended assessments exist — no strays, no empties for
        // columns a teacher left blank.
        $count = fn ($name) => Assessment::where('offering_id', $this->offering->id)
            ->where('term_id', $this->openTerm->id)
            ->where('subject_id', $this->subjects[$name]->id)->count();
        $this->assertSame(3, $count('Mathematics'));
        $this->assertSame(3, $count('English'));
        $this->assertSame(2, $count('Phonics'));   // single test + exam
        $this->assertSame(3, $count('French'));

        // Readiness stays quiet: nothing incomplete, nothing flagged as a stray
        // (Phonics has FEWER tests than the class norm, which is fine).
        $readiness = new ReportReadiness();
        $this->assertCount(0, $readiness->incompleteSubjects($this->offering, $this->openTerm, $this->school));
        $this->assertCount(0, $readiness->duplicateAssessments($this->offering, $this->openTerm, $this->school));
    }

    #[Test]
    public function the_grid_opens_on_the_current_term_even_when_an_old_term_holds_the_marks(): void
    {
        // Marks live in the old, ended term; today falls inside the open term.
        $this->offering->subjects($this->term->id)->save($this->subjects['Mathematics'], ['term_id' => $this->term->id]);
        $old = Assessment::factory()->create([
            'assessment_type_id' => \App\Models\AssessmentType::where('name', 'Test')->first()->id,
            'offering_id' => $this->offering->id, 'term_id' => $this->term->id,
            'subject_id' => $this->subjects['Mathematics']->id, 'max_score' => 100,
            'date' => \Illuminate\Support\Carbon::parse($this->term->start)->format('Y-m-d'),
        ]);
        \App\Models\AssessmentScore::factory()->create(['user_id' => $this->pupils[0]->id, 'assessment_id' => $old->id, 'score' => 70]);

        $this->actingAs($this->classTeacher)
            ->get(route('term-grid.edit', ['offering' => $this->offering, 'edit' => 1]))
            ->assertOk()
            ->assertSee('Open Term · Test 1'); // the edit badge: current term, first period
    }

    #[Test]
    public function a_fresh_term_opens_on_test_1_not_the_exam(): void
    {
        $this->actingAs($this->classTeacher)
            ->get(route('term-grid.edit', ['offering' => $this->offering, 'term' => $this->openTerm->id, 'edit' => 1]))
            ->assertOk()
            ->assertSeeInOrder(['Entering marks for', 'Test 1'], false);
    }

    #[Test]
    public function class_teacher_first_then_french_teacher(): void
    {
        $this->classTeacherSitting();
        $this->frenchTeacherSitting();

        $this->assertTermIsRight();
    }

    #[Test]
    public function french_teacher_first_then_class_teacher(): void
    {
        $this->frenchTeacherSitting();
        $this->classTeacherSitting();

        $this->assertTermIsRight();
    }

    #[Test]
    public function the_french_teacher_cannot_touch_other_columns_in_passing(): void
    {
        $this->classTeacherSitting();

        // A crafted payload with a Mathematics column: ignored server-side.
        $this->actingAs($this->frenchTeacher)->post(route('term-grid.save', $this->offering), [
            'term' => $this->openTerm->id,
            'month' => $this->periods['exam'],
            'scores' => [
                $this->subjects['French']->id => [$this->pupils[0]->id => '60', $this->pupils[1]->id => '30'],
                $this->subjects['Mathematics']->id => [$this->pupils[0]->id => '1'],
            ],
        ])->assertRedirect();

        $mathsExam = Assessment::where('offering_id', $this->offering->id)
            ->where('subject_id', $this->subjects['Mathematics']->id)
            ->whereMonth('date', (int) substr($this->periods['exam'], 5))->first();
        $this->assertSame(60, (int) $mathsExam->scores()->where('user_id', $this->pupils[0]->id)->value('score'));
    }

    #[Test]
    public function a_fresh_column_takes_the_tests_mode_default_max(): void
    {
        $this->saveGrid($this->classTeacher, 'test1', ['Mathematics' => [20, 10]]);
        $this->saveGrid($this->classTeacher, 'exam', ['Mathematics' => [70, 40]]);

        $max = fn (string $period) => (int) Assessment::where('offering_id', $this->offering->id)
            ->where('subject_id', $this->subjects['Mathematics']->id)
            ->whereMonth('date', (int) substr($this->periods[$period], 5))->value('max_score');
        $this->assertSame(25, $max('test1'));
        $this->assertSame(75, $max('exam'));

        // Tests-mode scales are fixed: a posted max override is ignored and the
        // column still lands on the school-wide default.
        $this->actingAs($this->classTeacher)->post(route('term-grid.save', $this->offering), [
            'term' => $this->openTerm->id,
            'month' => $this->periods['test1'],
            'scores' => [$this->subjects['Phonics']->id => [$this->pupils[0]->id => '18']],
            'max' => [$this->subjects['Phonics']->id => 20],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(25, (int) Assessment::where('offering_id', $this->offering->id)
            ->where('subject_id', $this->subjects['Phonics']->id)->value('max_score'));
    }
}
