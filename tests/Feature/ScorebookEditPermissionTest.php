<?php

namespace Tests\Feature;

use App\Models\AssessmentType;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Offering;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A subject teacher may only edit their own subjects' columns in the term grid.
 */
class ScorebookEditPermissionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_subject_teacher_can_only_save_their_assigned_subjects(): void
    {
        $school = School::first() ?? School::factory()->create();
        AssessmentType::factory()->test()->create(['school_id' => $school->id]);
        AssessmentType::factory()->exam()->create(['school_id' => $school->id]);

        $offering = Offering::factory()->create(['schoolyear_id' => $this->schoolyear->id, 'grade_id' => Grade::factory()->create()->id]);
        $maths = Subject::factory()->create(['name' => 'Mathematics']);
        $arts = Subject::factory()->create(['name' => 'Arts']);
        $offering->subjects($this->term->id)->save($maths, ['term_id' => $this->term->id]);
        $offering->subjects($this->term->id)->save($arts, ['term_id' => $this->term->id]);

        $student = Student::factory()->create();
        Enrollment::factory()->create(['user_id' => $student->id, 'offering_id' => $offering->id]);

        // This teacher is assigned only Mathematics for the class.
        DB::table('teacher_assignments')->insert([
            'user_id' => $this->teacher->id, 'offering_id' => $offering->id, 'subject_id' => $maths->id,
            'is_class_teacher' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $month = Carbon::parse($this->term->start)->format('Y-m');
        $this->actingAs($this->teacher)->post(route('term-grid.save', $offering), [
            'term' => $this->term->id, 'month' => $month, 'type' => 'Test',
            'scores' => [$maths->id => [$student->id => '80'], $arts->id => [$student->id => '90']],
        ])->assertRedirect();

        // Their subject saved; the other subject's column was ignored server-side.
        $this->assertDatabaseHas('assessments', ['offering_id' => $offering->id, 'subject_id' => $maths->id]);
        $this->assertDatabaseMissing('assessments', ['offering_id' => $offering->id, 'subject_id' => $arts->id]);
    }

    #[Test]
    public function a_teacher_with_no_specific_assignment_can_save_every_subject(): void
    {
        $school = School::first() ?? School::factory()->create();
        AssessmentType::factory()->test()->create(['school_id' => $school->id]);
        AssessmentType::factory()->exam()->create(['school_id' => $school->id]);

        $offering = Offering::factory()->create(['schoolyear_id' => $this->schoolyear->id, 'grade_id' => Grade::factory()->create()->id]);
        $maths = Subject::factory()->create(['name' => 'Mathematics']);
        $arts = Subject::factory()->create(['name' => 'Arts']);
        $offering->subjects($this->term->id)->save($maths, ['term_id' => $this->term->id]);
        $offering->subjects($this->term->id)->save($arts, ['term_id' => $this->term->id]);

        $student = Student::factory()->create();
        Enrollment::factory()->create(['user_id' => $student->id, 'offering_id' => $offering->id]);

        // No teacher_assignments rows -> general teacher, edits everything (unchanged behaviour).
        $month = Carbon::parse($this->term->start)->format('Y-m');
        $this->actingAs($this->headmaster)->post(route('term-grid.save', $offering), [
            'term' => $this->term->id, 'month' => $month, 'type' => 'Test',
            'scores' => [$maths->id => [$student->id => '80'], $arts->id => [$student->id => '90']],
        ])->assertRedirect();

        $this->assertDatabaseHas('assessments', ['offering_id' => $offering->id, 'subject_id' => $maths->id]);
        $this->assertDatabaseHas('assessments', ['offering_id' => $offering->id, 'subject_id' => $arts->id]);
    }
}
