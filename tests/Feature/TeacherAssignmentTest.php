<?php

namespace Tests\Feature;

use App\Models\Offering;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TeacherAssignmentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_assign_a_teacher_as_class_teacher()
    {
        $offering = Offering::factory()->create(['schoolyear_id' => $this->schoolyear->id]);

        $assignment = TeacherAssignment::create([
            'user_id' => $this->teacher->id,
            'offering_id' => $offering->id,
            'is_class_teacher' => true,
        ]);

        $this->assertTrue((bool) $assignment->is_class_teacher);
        $this->assertNull($assignment->subject_id);
        $this->assertEquals($this->teacher->id, $assignment->user->id);
    }

    #[Test]
    public function it_can_assign_a_teacher_to_a_specific_subject()
    {
        $offering = Offering::factory()->create(['schoolyear_id' => $this->schoolyear->id]);
        $subject = Subject::factory()->create();

        $assignment = TeacherAssignment::create([
            'user_id' => $this->teacher->id,
            'offering_id' => $offering->id,
            'subject_id' => $subject->id,
            'is_class_teacher' => false,
        ]);

        $this->assertFalse((bool) $assignment->is_class_teacher);
        $this->assertEquals($subject->id, $assignment->subject->id);
    }

    #[Test]
    public function it_can_assign_multiple_teachers_to_an_offering()
    {
        $offering = Offering::factory()->create(['schoolyear_id' => $this->schoolyear->id]);
        $subject1 = Subject::factory()->create();
        $subject2 = Subject::factory()->create();

        $teacher2 = \App\Models\User::create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@test.com',
            'password' => bcrypt('secret'),
        ]);
        $teacher2->assignRole('teacher');

        TeacherAssignment::create([
            'user_id' => $this->teacher->id,
            'offering_id' => $offering->id,
            'subject_id' => $subject1->id,
            'is_class_teacher' => true,
        ]);

        TeacherAssignment::create([
            'user_id' => $teacher2->id,
            'offering_id' => $offering->id,
            'subject_id' => $subject2->id,
            'is_class_teacher' => false,
        ]);

        $assignments = TeacherAssignment::where('offering_id', $offering->id)->get();
        $this->assertCount(2, $assignments);
    }
}
