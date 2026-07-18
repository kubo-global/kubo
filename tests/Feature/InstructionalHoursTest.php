<?php

namespace Tests\Feature;

use App\Http\Controllers\NewInterfaceControllers\InstructionalHoursController;
use App\Models\Grade;
use App\Models\InstructionalHour;
use App\Models\Lesson;
use App\Models\Offering;
use App\Models\Period;
use App\Models\School;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstructionalHoursTest extends TestCase
{
    use RefreshDatabase;

    /** A class whose Monday timetable has two 1-hour lessons (2h expected). */
    private function classWithTimetable(): Offering
    {
        $school = School::first() ?? School::factory()->create();
        $offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id'      => Grade::factory()->create()->id,
        ]);
        $subject = Subject::factory()->create();
        $p1 = Period::create(['school_id' => $school->id, 'label' => 'P1', 'start_time' => '08:00', 'end_time' => '09:00', 'is_break' => false, 'display_order' => 1]);
        $p2 = Period::create(['school_id' => $school->id, 'label' => 'P2', 'start_time' => '09:00', 'end_time' => '10:00', 'is_break' => false, 'display_order' => 2]);
        Lesson::create(['offering_id' => $offering->id, 'period_id' => $p1->id, 'day' => 1, 'length' => 1, 'subject_id' => $subject->id]);
        Lesson::create(['offering_id' => $offering->id, 'period_id' => $p2->id, 'day' => 1, 'length' => 1, 'subject_id' => $subject->id]);

        return $offering;
    }

    #[Test]
    public function expected_hours_come_from_the_timetable(): void
    {
        $offering = $this->classWithTimetable();
        $expected = InstructionalHoursController::expectedByWeekday($offering);

        $this->assertSame(2.0, $expected[1]); // Monday: two 1h lessons
        $this->assertSame(0.0, $expected[2]); // Tuesday: none scheduled
    }

    #[Test]
    public function a_teacher_can_log_actual_and_lost_hours(): void
    {
        $offering = $this->classWithTimetable();

        $this->actingAs($this->headmaster)
            ->post(route('instructional-hours.save', $offering), [
                'month' => '2026-06',
                'rows'  => ['2026-06-01' => ['actual' => '1.5', 'lost' => '0.5', 'remarks' => 'phone call']],
            ])->assertRedirect();

        $row = InstructionalHour::where('offering_id', $offering->id)->firstOrFail();
        $this->assertSame('2026-06-01', $row->date->toDateString());
        $this->assertSame(1.5, $row->actual_hours);
        $this->assertSame(0.5, $row->lost_hours);
        $this->assertSame('phone call', $row->remarks);
    }

    #[Test]
    public function clearing_a_days_values_removes_the_row(): void
    {
        $offering = $this->classWithTimetable();
        InstructionalHour::create(['offering_id' => $offering->id, 'date' => '2026-06-01', 'actual_hours' => 2]);

        $this->actingAs($this->headmaster)
            ->post(route('instructional-hours.save', $offering), [
                'month' => '2026-06',
                'rows'  => ['2026-06-01' => ['actual' => '', 'lost' => '', 'remarks' => '']],
            ])->assertRedirect();

        $this->assertDatabaseMissing('instructional_hours', ['offering_id' => $offering->id, 'date' => '2026-06-01']);
    }

    #[Test]
    public function the_log_page_renders_with_expected_hours(): void
    {
        $offering = $this->classWithTimetable();

        $this->actingAs($this->headmaster)
            ->get(route('instructional-hours.show', ['offering' => $offering, 'month' => '2026-06']))
            ->assertOk()
            ->assertSee('Instructional hours')
            ->assertSee('Expected');
    }

    #[Test]
    public function the_data_sheet_and_chart_download_as_pdfs(): void
    {
        $offering = $this->classWithTimetable();

        foreach (['instructional-hours.pdf', 'instructional-hours.chart'] as $routeName) {
            $response = $this->actingAs($this->headmaster)
                ->get(route($routeName, ['offering' => $offering, 'month' => '2026-06']));
            $response->assertOk();
            $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        }
    }

    #[Test]
    public function a_pupil_cannot_open_the_hours_log(): void
    {
        $offering = $this->classWithTimetable();

        $this->actingAs($this->student)
            ->get(route('instructional-hours.show', $offering))
            ->assertForbidden();
    }

    #[Test]
    public function the_log_groups_by_mon_fri_calendar_week(): void
    {
        $offering = $this->classWithTimetable();

        // May 2025 starts on a Thursday.
        $weeks = app(InstructionalHoursController::class)
            ->buildMonth($offering, \Illuminate\Support\Carbon::parse('2025-05-01'));

        $this->assertCount(2, $weeks[1]); // Thu 1, Fri 2
        $this->assertSame('05/05', $weeks[2][0]['date_short']); // week 2 starts Monday the 5th
        $this->assertCount(5, $weeks[2]);
    }
}
