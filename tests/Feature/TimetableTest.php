<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Offering;
use App\Models\Period;
use App\Models\School;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TimetableTest extends TestCase
{
    use RefreshDatabase;

    private function school(): School
    {
        return School::first() ?? School::factory()->create();
    }

    private function offering(): Offering
    {
        return Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id'      => Grade::factory()->create()->id,
        ]);
    }

    private function period(): Period
    {
        return Period::create([
            'school_id' => $this->school()->id,
            'label' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:40',
            'is_break' => false, 'display_order' => 1,
        ]);
    }

    #[Test]
    public function a_lesson_can_span_multiple_periods(): void
    {
        $offering = $this->offering();
        $school = $this->school();
        $p1 = Period::create(['school_id' => $school->id, 'label' => 'Period 1', 'is_break' => false, 'display_order' => 1]);
        $p2 = Period::create(['school_id' => $school->id, 'label' => 'Period 2', 'is_break' => false, 'display_order' => 2]);
        $subject = Subject::factory()->create(['name' => 'Mathematics']);

        $this->actingAs($this->headmaster)
            ->post(route('timetable.update', $offering), [
                'cells' => [$p1->id => [1 => ['subject_id' => $subject->id, 'length' => 2]]],
            ])
            ->assertRedirect();

        // The block is stored on the first period with length 2…
        $this->assertDatabaseHas('lessons', [
            'offering_id' => $offering->id, 'period_id' => $p1->id, 'day' => 1, 'length' => 2, 'subject_id' => $subject->id,
        ]);
        // …and the covered period has no lesson of its own.
        $this->assertDatabaseMissing('lessons', ['offering_id' => $offering->id, 'period_id' => $p2->id, 'day' => 1]);
    }

    #[Test]
    public function a_headmaster_can_view_a_class_timetable(): void
    {
        $offering = $this->offering();
        $this->period();

        $this->actingAs($this->headmaster)
            ->get(route('timetable.show', $offering))
            ->assertOk()
            ->assertSee('Timetable')
            ->assertSee('Period 1');
    }

    #[Test]
    public function the_timetable_index_lists_classes_to_pick(): void
    {
        $grade = Grade::factory()->create(['name' => 'Grade 5']);
        Offering::factory()->create(['schoolyear_id' => $this->schoolyear->id, 'grade_id' => $grade->id]);

        $this->actingAs($this->headmaster)
            ->get(route('timetable.index'))
            ->assertOk()
            ->assertSee('Timetables')
            ->assertSee('Grade 5');
    }

    #[Test]
    public function the_timetable_is_read_only_until_edit_mode(): void
    {
        $offering = $this->offering();
        $this->period();

        // Default: read-only — an Edit button, no cell inputs.
        $this->actingAs($this->headmaster)
            ->get(route('timetable.show', $offering))
            ->assertOk()
            ->assertSee('Edit timetable')
            ->assertDontSee('name="cells[', false);

        // ?edit=1: the editable grid with the cell selects + Save.
        $this->actingAs($this->headmaster)
            ->get(route('timetable.show', ['offering' => $offering, 'edit' => 1]))
            ->assertOk()
            ->assertSee('Save timetable')
            ->assertSee('name="cells[', false);
    }

    #[Test]
    public function a_period_can_be_added_in_settings(): void
    {
        $this->school();

        $this->actingAs($this->headmaster)
            ->post(route('settings.store-period'), ['label' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:40'])
            ->assertRedirect();

        $this->assertDatabaseHas('periods', ['label' => 'Period 1']);
    }

    #[Test]
    public function saving_the_timetable_creates_a_lesson(): void
    {
        $offering = $this->offering();
        $period = $this->period();
        $subject = Subject::factory()->create(['name' => 'Mathematics']);

        $this->actingAs($this->headmaster)
            ->post(route('timetable.update', $offering), [
                'cells' => [$period->id => [1 => ['subject_id' => $subject->id]]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lessons', [
            'offering_id' => $offering->id, 'period_id' => $period->id, 'day' => 1, 'subject_id' => $subject->id,
        ]);
    }

    #[Test]
    public function clearing_a_cell_removes_the_lesson(): void
    {
        $offering = $this->offering();
        $period = $this->period();
        $subject = Subject::factory()->create();
        Lesson::create(['offering_id' => $offering->id, 'period_id' => $period->id, 'day' => 1, 'subject_id' => $subject->id]);

        $this->actingAs($this->headmaster)
            ->post(route('timetable.update', $offering), [
                'cells' => [$period->id => [1 => ['subject_id' => '']]],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('lessons', [
            'offering_id' => $offering->id, 'period_id' => $period->id, 'day' => 1,
        ]);
    }

    #[Test]
    public function a_plain_teacher_cannot_manage_periods(): void
    {
        $this->school();

        $this->actingAs($this->teacher)
            ->post(route('settings.store-period'), ['label' => 'Sneaky'])
            ->assertForbidden();

        $this->assertDatabaseMissing('periods', ['label' => 'Sneaky']);
    }
}
