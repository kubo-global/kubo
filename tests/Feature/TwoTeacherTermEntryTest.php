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
        $this->saveGrid($this->classTeacher, 'test1', [
            'Mathematics' => [84, 60], 'English' => [70, 50], 'Phonics' => [80, 40],
        ]);
        $this->saveGrid($this->classTeacher, 'test2', [
            'Mathematics' => [56, 40], 'English' => [90, 70],
        ]);
        $this->saveGrid($this->classTeacher, 'exam', [
            'Mathematics' => [80, 60], 'English' => [60, 80], 'Phonics' => [60, 80],
        ]);
    }

    /** The French teacher's sitting: same periods, French only. */
    private function frenchTeacherSitting(): void
    {
        $this->saveGrid($this->frenchTeacher, 'test1', ['French' => [90, 50]]);
        $this->saveGrid($this->frenchTeacher, 'test2', ['French' => [70, 30]]);
        $this->saveGrid($this->frenchTeacher, 'exam', ['French' => [80, 40]]);
    }

    private function assertTermIsRight(): void
    {
        $enrollment = Enrollment::where('user_id', $this->pupils[0]->id)->firstOrFail();
        $results = (new NewTermReportRepository($enrollment, $this->openTerm, $this->school))
            ->getReportData()['results'];

        // Pupil 1 — weighted 0.25/0.75:
        // Mathematics: tests (84+56)/2=70 -> 18; exam 80 -> 60; total 78.
        // English:     tests (70+90)/2=80 -> 20; exam 60 -> 45; total 65.
        // Phonics:     single test 80    -> 20; exam 60 -> 45; total 65.
        // French:      tests (90+70)/2=80 -> 20; exam 80 -> 60; total 80.
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
                $this->subjects['French']->id => [$this->pupils[0]->id => '80', $this->pupils[1]->id => '40'],
                $this->subjects['Mathematics']->id => [$this->pupils[0]->id => '1'],
            ],
        ])->assertRedirect();

        $mathsExam = Assessment::where('offering_id', $this->offering->id)
            ->where('subject_id', $this->subjects['Mathematics']->id)
            ->whereMonth('date', (int) substr($this->periods['exam'], 5))->first();
        $this->assertSame(80, (int) $mathsExam->scores()->where('user_id', $this->pupils[0]->id)->value('score'));
    }
}
