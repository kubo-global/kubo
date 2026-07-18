<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\LessonPlan;
use App\Models\Offering;
use App\Models\Schoolyear;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LessonPlanTest extends TestCase
{
    use RefreshDatabase;

    private Offering $offering;
    private Subject $subject;
    private User $assistantCoordinator;

    public function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'assistant_coordinator']);

        $grade = Grade::factory()->create(['name' => 'Grade 3']);
        $this->offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id' => $grade->id,
        ]);
        $this->subject = Subject::create(['name' => 'Mathematics']);

        DB::table('teacher_offering')->insert([
            'user_id' => $this->teacher->id,
            'offering_id' => $this->offering->id,
            'principal' => true,
        ]);

        $this->assistantCoordinator = User::create(['first_name' => 'Sulayman', 'last_name' => 'Janneh', 'password' => bcrypt('x')]);
        $this->assistantCoordinator->assignRole('assistant_coordinator');
    }

    #[Test]
    public function teacher_can_create_a_lesson_plan_for_their_own_class()
    {
        $this->actingAs($this->teacher)
            ->post(route('lesson-plans.store'), [
                'offering_id' => $this->offering->id,
                'subject_id' => $this->subject->id,
                'lesson_date' => '2026-05-06',
                'topic' => 'Fractions',
                'content' => 'Halves and quarters',
                'objectives' => '1. Identify halves',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lesson_plans', [
            'user_id' => $this->teacher->id,
            'topic' => 'Fractions',
        ]);
    }

    #[Test]
    public function teacher_cannot_edit_another_teachers_plan()
    {
        $other = User::create(['first_name' => 'Other', 'last_name' => 'Teacher', 'password' => bcrypt('x')]);
        $other->assignRole('teacher');

        $plan = LessonPlan::create([
            'user_id' => $other->id,
            'offering_id' => $this->offering->id,
            'subject_id' => $this->subject->id,
            'lesson_date' => '2026-05-06',
            'topic' => 'Original',
        ]);

        $this->actingAs($this->teacher)
            ->get(route('lesson-plans.edit', $plan))
            ->assertForbidden();
    }

    #[Test]
    public function headmaster_can_quick_sign_coordinator_remarks_without_touching_the_lesson_body()
    {
        $plan = LessonPlan::create([
            'user_id' => $this->teacher->id,
            'offering_id' => $this->offering->id,
            'subject_id' => $this->subject->id,
            'lesson_date' => '2026-05-06',
            'topic' => 'Original topic',
            'content' => 'Original content',
        ]);

        // Quick-sign request from the show page only carries coordinator_remarks.
        $this->actingAs($this->headmaster)
            ->put(route('lesson-plans.update', $plan), [
                'coordinator_remarks' => 'Looks great.',
            ])
            ->assertRedirect();

        $plan->refresh();
        $this->assertSame('Original topic', $plan->topic, 'Quick sign-off must not wipe the lesson body.');
        $this->assertSame('Original content', $plan->content);
        $this->assertSame('Looks great.', $plan->coordinator_remarks);
    }

    #[Test]
    public function assistant_coordinator_can_only_write_their_own_remarks_field()
    {
        $plan = LessonPlan::create([
            'user_id' => $this->teacher->id,
            'offering_id' => $this->offering->id,
            'subject_id' => $this->subject->id,
            'lesson_date' => '2026-05-06',
            'topic' => 'Topic',
        ]);

        $this->actingAs($this->assistantCoordinator)
            ->put(route('lesson-plans.update', $plan), [
                'coordinator_remarks' => 'Trying to write coord field',
                'assistant_coordinator_remarks' => 'Reviewed.',
            ])
            ->assertRedirect();

        $plan->refresh();
        $this->assertSame('Reviewed.', $plan->assistant_coordinator_remarks);
        $this->assertNull($plan->coordinator_remarks);
    }

    #[Test]
    public function teacher_only_sees_own_plans_in_index()
    {
        $other = User::create(['first_name' => 'Other', 'last_name' => 'Teacher', 'password' => bcrypt('x')]);
        $other->assignRole('teacher');

        LessonPlan::create([
            'user_id' => $this->teacher->id,
            'offering_id' => $this->offering->id,
            'subject_id' => $this->subject->id,
            'lesson_date' => '2026-05-06',
            'topic' => 'Mine',
        ]);
        LessonPlan::create([
            'user_id' => $other->id,
            'offering_id' => $this->offering->id,
            'subject_id' => $this->subject->id,
            'lesson_date' => '2026-05-06',
            'topic' => 'Theirs',
        ]);

        $this->actingAs($this->teacher)
            ->get(route('lesson-plans.index'))
            ->assertOk()
            ->assertSeeText('Mine')
            ->assertDontSeeText('Theirs');
    }

    #[Test]
    public function headmaster_sees_all_plans_in_index()
    {
        $other = User::create(['first_name' => 'Other', 'last_name' => 'Teacher', 'password' => bcrypt('x')]);
        $other->assignRole('teacher');

        LessonPlan::create([
            'user_id' => $this->teacher->id,
            'offering_id' => $this->offering->id,
            'subject_id' => $this->subject->id,
            'lesson_date' => '2026-05-06',
            'topic' => 'Mine',
        ]);
        LessonPlan::create([
            'user_id' => $other->id,
            'offering_id' => $this->offering->id,
            'subject_id' => $this->subject->id,
            'lesson_date' => '2026-05-06',
            'topic' => 'Theirs',
        ]);

        $this->actingAs($this->headmaster)
            ->get(route('lesson-plans.index'))
            ->assertOk()
            ->assertSeeText('Mine')
            ->assertSeeText('Theirs');
    }

    #[Test]
    public function student_cannot_access_lesson_plans()
    {
        $this->actingAs($this->student)
            ->get(route('lesson-plans.index'))
            ->assertForbidden();
    }
}
