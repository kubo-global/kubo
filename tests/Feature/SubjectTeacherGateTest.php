<?php

namespace Tests\Feature;

use App\Models\AssessmentType;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Offering;
use App\Models\School;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The grade-entry gate. Default for a teacher attached to a class is
 * "can grade any subject in this class" (the generalist primary-school
 * teacher model). Subject-specific assignments act as LOCKS — if a
 * specific teacher is named for (offering, subject), only they can
 * grade it. Headmaster/admin/system_admin and class principals always
 * bypass.
 */
class SubjectTeacherGateTest extends TestCase
{
    use RefreshDatabase;

    private Offering $offering;
    private Subject $math;
    private Subject $french;
    private User $frenchTeacher;
    private User $generalist;
    private User $principal;
    private AssessmentType $type;

    public function setUp(): void
    {
        parent::setUp();

        $school = School::factory()->create();
        $grade = Grade::factory()->create();
        $this->offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id' => $grade->id,
        ]);

        // Extend the test term so AssessmentController's locked-term guard
        // doesn't block writes on the historical TestCase term.
        $this->term->update(['end' => now()->addMonth()->toDateString()]);

        $this->math = Subject::create(['name' => 'Mathematics']);
        $this->french = Subject::create(['name' => 'French']);

        // Both subjects taught in this class this term.
        foreach ([$this->math, $this->french] as $subject) {
            DB::table('subject_term_offering')->insert([
                'subject_id' => $subject->id,
                'term_id' => $this->term->id,
                'offering_id' => $this->offering->id,
            ]);
        }

        $this->frenchTeacher = User::create(['first_name' => 'Mariam', 'last_name' => 'Sow', 'password' => bcrypt('x')]);
        $this->frenchTeacher->assignRole('teacher');

        // Generalist primary-school teacher — attached to the class via
        // teacher_offering, no subject assignment.
        $this->generalist = User::create(['first_name' => 'Fatou', 'last_name' => 'Ba', 'password' => bcrypt('x')]);
        $this->generalist->assignRole('teacher');
        DB::table('teacher_offering')->insert([
            'user_id' => $this->generalist->id,
            'offering_id' => $this->offering->id,
            'principal' => false,
        ]);

        $this->principal = User::create(['first_name' => 'Aliou', 'last_name' => 'Diop', 'password' => bcrypt('x')]);
        $this->principal->assignRole('teacher');

        DB::table('teacher_offering')->insert([
            'user_id' => $this->principal->id,
            'offering_id' => $this->offering->id,
            'principal' => true,
        ]);

        $this->type = AssessmentType::create([
            'school_id' => $school->id,
            'name' => 'Test',
            'weight' => 0.25,
            'display_order' => 1,
        ]);
    }

    #[Test]
    public function french_teacher_with_assignment_can_post_french_assessment()
    {
        TeacherAssignment::create([
            'user_id' => $this->frenchTeacher->id,
            'offering_id' => $this->offering->id,
            'subject_id' => $this->french->id,
            'is_class_teacher' => false,
        ]);

        $this->actingAs($this->frenchTeacher)
            ->post(route('reporting.assessment.store'), [
                'assessment_type_id' => $this->type->id,
                'offering_id' => $this->offering->id,
                'term_id' => $this->term->id,
                'subject_id' => $this->french->id,
                'name' => 'Test 1',
                'date' => now()->toDateString(),
                'max_score' => 25,
            ])
            ->assertRedirect();
    }

    #[Test]
    public function generalist_can_grade_subjects_without_a_specific_assignment()
    {
        // Fatou is class-attached but has no subject assignments. With
        // no French/Math teacher named, she can post for either.
        $this->actingAs($this->generalist)
            ->post(route('reporting.assessment.store'), [
                'assessment_type_id' => $this->type->id,
                'offering_id' => $this->offering->id,
                'term_id' => $this->term->id,
                'subject_id' => $this->math->id,
                'name' => 'Quiz 1',
                'date' => now()->toDateString(),
                'max_score' => 25,
            ])
            ->assertRedirect();
    }

    #[Test]
    public function generalist_cannot_grade_a_subject_locked_by_assignment()
    {
        // Mariam owns French — Fatou can't post French grades even though
        // she's the class-attached generalist.
        TeacherAssignment::create([
            'user_id' => $this->frenchTeacher->id,
            'offering_id' => $this->offering->id,
            'subject_id' => $this->french->id,
            'is_class_teacher' => false,
        ]);

        $this->actingAs($this->generalist)
            ->post(route('reporting.assessment.store'), [
                'assessment_type_id' => $this->type->id,
                'offering_id' => $this->offering->id,
                'term_id' => $this->term->id,
                'subject_id' => $this->french->id,
                'name' => 'Test 1',
                'date' => now()->toDateString(),
                'max_score' => 25,
            ])
            ->assertForbidden();
    }

    #[Test]
    public function generalist_can_still_grade_unlocked_subjects_when_a_different_one_is_locked()
    {
        TeacherAssignment::create([
            'user_id' => $this->frenchTeacher->id,
            'offering_id' => $this->offering->id,
            'subject_id' => $this->french->id,
            'is_class_teacher' => false,
        ]);

        // Math has no specific teacher — Fatou still grades it.
        $this->actingAs($this->generalist)
            ->post(route('reporting.assessment.store'), [
                'assessment_type_id' => $this->type->id,
                'offering_id' => $this->offering->id,
                'term_id' => $this->term->id,
                'subject_id' => $this->math->id,
                'name' => 'Quiz 1',
                'date' => now()->toDateString(),
                'max_score' => 25,
            ])
            ->assertRedirect();
    }

    #[Test]
    public function class_principal_can_post_any_subject_in_their_class()
    {
        $this->actingAs($this->principal)
            ->post(route('reporting.assessment.store'), [
                'assessment_type_id' => $this->type->id,
                'offering_id' => $this->offering->id,
                'term_id' => $this->term->id,
                'subject_id' => $this->math->id,
                'name' => 'Quiz 1',
                'date' => now()->toDateString(),
                'max_score' => 25,
            ])
            ->assertRedirect();
    }

    #[Test]
    public function headmaster_bypasses_the_gate()
    {
        $this->actingAs($this->headmaster)
            ->post(route('reporting.assessment.store'), [
                'assessment_type_id' => $this->type->id,
                'offering_id' => $this->offering->id,
                'term_id' => $this->term->id,
                'subject_id' => $this->math->id,
                'name' => 'Quiz 1',
                'date' => now()->toDateString(),
                'max_score' => 25,
            ])
            ->assertRedirect();
    }

    #[Test]
    public function multiple_teachers_can_share_a_subject_lock()
    {
        // Both Mariam and Fatou are assigned to French. Both should be
        // able to post French grades; the principal still can too.
        TeacherAssignment::create([
            'user_id' => $this->frenchTeacher->id,
            'offering_id' => $this->offering->id,
            'subject_id' => $this->french->id,
            'is_class_teacher' => false,
        ]);
        TeacherAssignment::create([
            'user_id' => $this->generalist->id,
            'offering_id' => $this->offering->id,
            'subject_id' => $this->french->id,
            'is_class_teacher' => false,
        ]);

        foreach ([$this->frenchTeacher, $this->generalist] as $actor) {
            $this->actingAs($actor)
                ->post(route('reporting.assessment.store'), [
                    'assessment_type_id' => $this->type->id,
                    'offering_id' => $this->offering->id,
                    'term_id' => $this->term->id,
                    'subject_id' => $this->french->id,
                    'name' => 'Test ' . $actor->id,
                    'date' => now()->toDateString(),
                    'max_score' => 25,
                ])
                ->assertRedirect();
        }
    }

    #[Test]
    public function teacher_with_no_class_attachment_and_no_assignment_is_blocked()
    {
        // Mariam isn't class-attached and has no subject assignment.
        // Even though French has no subject teacher named, she still
        // can't grade — the generalist default requires class attachment.
        $this->actingAs($this->frenchTeacher)
            ->post(route('reporting.assessment.store'), [
                'assessment_type_id' => $this->type->id,
                'offering_id' => $this->offering->id,
                'term_id' => $this->term->id,
                'subject_id' => $this->french->id,
                'name' => 'Test 1',
                'date' => now()->toDateString(),
                'max_score' => 25,
            ])
            ->assertForbidden();
    }
}
