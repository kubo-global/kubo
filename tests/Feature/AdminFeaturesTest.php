<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AssessmentType;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Offering;
use App\Models\School;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private Offering $offering;
    private User $enrolledStudent;

    public function setUp(): void
    {
        parent::setUp();

        // The base TestCase term ends 2024-12-31 — long in the past, which
        // trips the locked-term guard on every assessment write. Extend its
        // end date so the teacher (non-admin) can post against it.
        $this->term->update(['end' => now()->addMonth()->toDateString()]);

        $this->school = School::create(['name' => 'Test School']);

        $grade = Grade::factory()->create(['name' => 'Grade 1']);

        $this->offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id' => $grade->id,
        ]);

        DB::table('teacher_offering')->insert([
            'user_id' => $this->teacher->id,
            'offering_id' => $this->offering->id,
            'principal' => true,
        ]);

        $this->enrolledStudent = User::create([
            'first_name' => 'Fatou',
            'last_name' => 'Jallow',
            'password' => bcrypt('secret'),
        ]);
        $this->enrolledStudent->assignRole('student');

        Enrollment::create([
            'user_id' => $this->enrolledStudent->id,
            'offering_id' => $this->offering->id,
        ]);

        // Create subject-term-offering mapping
        DB::table('subject_term_offering')->insert([
            'subject_id' => Subject::create(['name' => 'Mathematics'])->id,
            'term_id' => $this->term->id,
            'offering_id' => $this->offering->id,
        ]);
    }

    // ===================== STUDENT MANAGEMENT =====================

    #[Test]
    public function test_teacher_can_view_students_list()
    {
        $this->actingAs($this->teacher)
            ->get(route('students.index'))
            ->assertOk();
    }

    #[Test]
    public function test_teacher_can_view_student_detail()
    {
        $this->actingAs($this->teacher)
            ->get(route('students.show', $this->enrolledStudent))
            ->assertOk()
            ->assertSee('Fatou');
    }

    #[Test]
    public function test_student_without_enrollment_redirects()
    {
        $noEnrollment = User::create([
            'first_name' => 'Ghost',
            'last_name' => 'Student',
            'password' => bcrypt('secret'),
        ]);
        $noEnrollment->assignRole('student');

        $this->actingAs($this->teacher)
            ->get(route('students.show', $noEnrollment))
            ->assertRedirect();
    }

    // ===================== ASSESSMENT FLOW =====================

    #[Test]
    public function test_assessment_full_flow_create_scores_confirm()
    {
        $type = AssessmentType::create([
            'school_id' => $this->school->id,
            'name' => 'Test',
            'weight' => 0.25,
            'display_order' => 1,
        ]);

        // Step 1: Create page loads
        $this->actingAs($this->teacher)
            ->get(route('reporting.assessment.create', $type->id))
            ->assertOk();

        // Step 1: POST creates draft assessment
        $subject = Subject::first();
        $response = $this->actingAs($this->teacher)
            ->post(route('reporting.assessment.store'), [
                'assessment_type_id' => $type->id,
                'offering_id' => $this->offering->id,
                'term_id' => $this->term->id,
                'subject_id' => $subject->id,
                'name' => 'Week 1 Test',
                'date' => '2025-10-01',
                'max_score' => 50,
            ])
            ->assertRedirect();

        $assessment = Assessment::where('name', 'Week 1 Test')->first();
        $this->assertNotNull($assessment);
        $this->assertFalse($assessment->confirmed);

        // Step 2: Scores page loads
        $this->actingAs($this->teacher)
            ->get(route('reporting.assessment.scores', $assessment))
            ->assertOk()
            ->assertSee('Fatou');

        // Step 2: POST saves scores
        $this->actingAs($this->teacher)
            ->post(route('reporting.assessment.saveScores', $assessment), [
                'scores' => [$this->enrolledStudent->id => 42],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('assessment_scores', [
            'assessment_id' => $assessment->id,
            'user_id' => $this->enrolledStudent->id,
            'score' => 42,
            'absent' => false,
        ]);

        // Step 3: Confirm page loads
        $this->actingAs($this->teacher)
            ->get(route('reporting.assessment.confirm', $assessment))
            ->assertOk()
            ->assertSee('42');

        // Step 3: POST confirms
        $this->actingAs($this->teacher)
            ->post(route('reporting.assessment.finalize', $assessment))
            ->assertRedirect();

        $assessment->refresh();
        $this->assertTrue($assessment->confirmed);
    }

    #[Test]
    public function test_assessment_with_absent_student()
    {
        $type = AssessmentType::create([
            'school_id' => $this->school->id,
            'name' => 'Test',
            'weight' => 0.25,
        ]);

        $assessment = Assessment::create([
            'assessment_type_id' => $type->id,
            'offering_id' => $this->offering->id,
            'term_id' => $this->term->id,
            'subject_id' => Subject::first()->id,
            'name' => 'Test',
            'max_score' => 100,
            'confirmed' => false,
        ]);

        $this->actingAs($this->teacher)
            ->post(route('reporting.assessment.saveScores', $assessment), [
                'scores' => [$this->enrolledStudent->id => ''],
                'absent' => [$this->enrolledStudent->id => 'on'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('assessment_scores', [
            'assessment_id' => $assessment->id,
            'user_id' => $this->enrolledStudent->id,
            'score' => null,
            'absent' => true,
        ]);
    }

    #[Test]
    public function test_assessment_validation_requires_score_or_absent()
    {
        $type = AssessmentType::create([
            'school_id' => $this->school->id,
            'name' => 'Test',
            'weight' => 0.25,
        ]);

        $assessment = Assessment::create([
            'assessment_type_id' => $type->id,
            'offering_id' => $this->offering->id,
            'term_id' => $this->term->id,
            'subject_id' => Subject::first()->id,
            'name' => 'Test',
            'max_score' => 100,
            'confirmed' => false,
        ]);

        // No score and not absent — should fail
        $this->actingAs($this->teacher)
            ->post(route('reporting.assessment.saveScores', $assessment), [
                'scores' => [$this->enrolledStudent->id => ''],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('validation');
    }

    #[Test]
    public function test_assessment_delete()
    {
        $type = AssessmentType::create([
            'school_id' => $this->school->id,
            'name' => 'Test',
            'weight' => 0.25,
        ]);

        $assessment = Assessment::create([
            'assessment_type_id' => $type->id,
            'offering_id' => $this->offering->id,
            'term_id' => $this->term->id,
            'subject_id' => Subject::first()->id,
            'name' => 'Delete Me',
            'max_score' => 100,
            'confirmed' => true,
        ]);

        $this->actingAs($this->teacher)
            ->delete(route('reporting.assessment.delete', $assessment))
            ->assertRedirect();

        $this->assertDatabaseMissing('assessments', ['id' => $assessment->id]);
    }

    // ===================== TERM REPORTS =====================

    #[Test]
    public function test_term_report_view_loads()
    {
        $this->actingAs($this->teacher)
            ->get(route('term-report.show', [
                'enrollmentId' => Enrollment::first()->id,
                'termId' => $this->term->id,
            ]))
            ->assertOk()
            ->assertSee('Fatou');
    }

    // ===================== SCOREBOOK =====================

    #[Test]
    public function test_scorebook_loads()
    {
        $this->actingAs($this->teacher)
            ->get(route('reporting.grades'))
            ->assertOk();
    }

    #[Test]
    public function test_scoresheet_create_shows_assessment_types()
    {
        AssessmentType::create([
            'school_id' => $this->school->id,
            'name' => 'Test',
            'weight' => 0.25,
        ]);
        AssessmentType::create([
            'school_id' => $this->school->id,
            'name' => 'Exam',
            'weight' => 0.75,
        ]);

        $this->actingAs($this->teacher)
            ->get(route('scorebook.create'))
            ->assertOk()
            ->assertSee('Test')
            ->assertSee('Exam');
    }

    // ===================== PROGRESS DASHBOARD =====================

    #[Test]
    public function test_progress_index_for_teacher()
    {
        $this->actingAs($this->teacher)
            ->get(route('progress.index'))
            ->assertOk();
    }

    #[Test]
    public function test_progress_show_for_offering()
    {
        $this->actingAs($this->teacher)
            ->get(route('progress.show', $this->offering))
            ->assertOk()
            ->assertSee('Fatou');
    }

    // ===================== ACCESS CONTROL =====================

    #[Test]
    public function test_student_cannot_access_admin_routes()
    {
        $this->actingAs($this->enrolledStudent)
            ->get(route('progress.index'))
            ->assertForbidden();

        $this->actingAs($this->enrolledStudent)
            ->get(route('exercises.index'))
            ->assertForbidden();

        $this->actingAs($this->enrolledStudent)
            ->get(route('content.index'))
            ->assertForbidden();
    }

    #[Test]
    public function test_student_login_blocks_non_students()
    {
        $this->get(route('student-login.login', $this->teacher))
            ->assertForbidden();
    }

    #[Test]
    public function test_student_login_allows_students()
    {
        $this->get(route('student-login.login', $this->enrolledStudent))
            ->assertRedirect(route('learn.index'));
    }

    // ===================== CONTACT (bug fix verification) =====================

    #[Test]
    public function test_contact_show_does_not_dd()
    {
        $contact = \App\Models\Contact::create([
            'first_name' => 'Parent',
            'last_name' => 'Name',
        ]);
        $contact->users()->attach($this->enrolledStudent->id);

        $this->actingAs($this->teacher)
            ->get("/students/{$this->enrolledStudent->id}/contacts/{$contact->id}")
            ->assertOk();
    }

    // ===================== DASHBOARD =====================

    #[Test]
    public function test_teacher_dashboard_loads()
    {
        $this->actingAs($this->teacher)
            ->get(route('home'))
            ->assertOk();
    }

    #[Test]
    public function test_dashboard_handles_no_schoolyear()
    {
        // Delete all school years
        DB::table('terms')->delete();
        DB::table('enrollments')->delete();
        DB::table('offerings')->delete();
        DB::table('schoolyears')->delete();

        $this->actingAs($this->teacher)
            ->get(route('home'))
            ->assertOk();
    }
}
