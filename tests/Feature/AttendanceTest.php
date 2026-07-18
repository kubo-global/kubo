<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\Offering;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    public function setUp(): void
    {
        parent::setUp();
        $this->school = School::factory()->create();
    }

    #[Test]
    public function it_can_record_student_attendance()
    {
        $offering = Offering::factory()->create(['schoolyear_id' => $this->schoolyear->id]);

        $attendance = Attendance::create([
            'school_id' => $this->school->id,
            'user_id' => $this->student->id,
            'offering_id' => $offering->id,
            'date' => '2024-10-01',
            'status' => AttendanceStatus::Present,
            'type' => AttendanceType::Student,
            'recorded_by' => $this->teacher->id,
        ]);

        $this->assertEquals(AttendanceStatus::Present, $attendance->status);
        $this->assertEquals(AttendanceType::Student, $attendance->type);
        $this->assertEquals($this->student->id, $attendance->user->id);
    }

    #[Test]
    public function it_can_record_teacher_attendance()
    {
        $attendance = Attendance::create([
            'school_id' => $this->school->id,
            'user_id' => $this->teacher->id,
            'date' => '2024-10-01',
            'status' => AttendanceStatus::Late,
            'type' => AttendanceType::Teacher,
            'recorded_by' => $this->headmaster->id,
        ]);

        $this->assertEquals(AttendanceStatus::Late, $attendance->status);
        $this->assertEquals(AttendanceType::Teacher, $attendance->type);
    }

    #[Test]
    public function it_can_query_attendance_by_date_and_type()
    {
        Attendance::factory()->create([
            'school_id' => $this->school->id,
            'user_id' => $this->student->id,
            'date' => '2024-10-01',
            'status' => AttendanceStatus::Present,
            'type' => AttendanceType::Student,
        ]);
        Attendance::factory()->create([
            'school_id' => $this->school->id,
            'user_id' => $this->teacher->id,
            'date' => '2024-10-01',
            'status' => AttendanceStatus::Present,
            'type' => AttendanceType::Teacher,
        ]);
        Attendance::factory()->create([
            'school_id' => $this->school->id,
            'user_id' => $this->student->id,
            'date' => '2024-10-02',
            'status' => AttendanceStatus::Absent,
            'type' => AttendanceType::Student,
        ]);

        $studentAttendance = Attendance::whereDate('date', '2024-10-01')
            ->where('type', AttendanceType::Student)
            ->get();

        $this->assertCount(1, $studentAttendance);

        $allOnDate = Attendance::whereDate('date', '2024-10-01')->get();
        $this->assertCount(2, $allOnDate);
    }

    // --- Daily register UI ---

    /** @return array{0: Offering, 1: \App\Models\Student} */
    private function classWithPupil(): array
    {
        $offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id'      => \App\Models\Grade::factory()->create()->id,
        ]);
        $pupil = \App\Models\Student::factory()->create(['first_name' => 'Awa', 'last_name' => 'Dukureh']);
        \App\Models\Enrollment::factory()->create(['user_id' => $pupil->id, 'offering_id' => $offering->id]);
        return [$offering, $pupil];
    }

    #[Test]
    public function a_headmaster_can_view_the_attendance_register()
    {
        [$offering] = $this->classWithPupil();

        $this->actingAs($this->headmaster)
            ->get(route('scorebook.attendance', $offering))
            ->assertOk()
            ->assertSee('Attendance')
            ->assertSee('Dukureh');
    }

    #[Test]
    public function attendance_can_be_recorded_for_a_day()
    {
        [$offering, $pupil] = $this->classWithPupil();

        $this->actingAs($this->headmaster)
            ->post(route('attendance.save', $offering), [
                'date' => '2026-05-04',
                'status' => [$pupil->id => 'absent'],
            ])
            ->assertRedirect();

        $this->assertTrue(
            Attendance::where('offering_id', $offering->id)->where('user_id', $pupil->id)
                ->whereDate('date', '2026-05-04')->where('status', 'absent')->where('type', 'student')->exists()
        );
    }

    #[Test]
    public function recording_again_updates_the_same_day()
    {
        [$offering, $pupil] = $this->classWithPupil();
        $post = fn ($status) => $this->actingAs($this->headmaster)->post(route('attendance.save', $offering), [
            'date' => '2026-05-04', 'status' => [$pupil->id => $status],
        ]);

        $post('absent');
        $post('present');

        $this->assertSame(1, Attendance::where('user_id', $pupil->id)->whereDate('date', '2026-05-04')->count());
        $this->assertTrue(
            Attendance::where('user_id', $pupil->id)->whereDate('date', '2026-05-04')->where('status', 'present')->exists()
        );
    }

    #[Test]
    public function a_pupil_cannot_open_the_register()
    {
        [$offering] = $this->classWithPupil();

        $this->actingAs($this->student)
            ->get(route('scorebook.attendance', $offering))
            ->assertForbidden();
    }

    #[Test]
    public function the_monthly_tally_counts_boys_and_girls_present_and_absent()
    {
        [$offering] = $this->classWithPupil();
        $boy = \App\Models\Student::factory()->create();
        $boy->profile()->create(['gender' => 'M']);
        \App\Models\Enrollment::factory()->create(['user_id' => $boy->id, 'offering_id' => $offering->id]);
        $girl = \App\Models\Student::factory()->create();
        $girl->profile()->create(['gender' => 'F']);
        \App\Models\Enrollment::factory()->create(['user_id' => $girl->id, 'offering_id' => $offering->id]);

        // 2026-05-04 is a Monday — boy present, girl absent.
        Attendance::create(['school_id' => $this->school->id, 'user_id' => $boy->id, 'offering_id' => $offering->id, 'date' => '2026-05-04', 'status' => AttendanceStatus::Present, 'type' => AttendanceType::Student]);
        Attendance::create(['school_id' => $this->school->id, 'user_id' => $girl->id, 'offering_id' => $offering->id, 'date' => '2026-05-04', 'status' => AttendanceStatus::Absent, 'type' => AttendanceType::Student]);

        $weeks = \App\Http\Controllers\NewInterfaceControllers\AttendanceController::monthTally($offering->id, \Illuminate\Support\Carbon::parse('2026-05-01'));
        $monday = collect($weeks)->flatten(1)->firstWhere('date', '04/05');

        $this->assertNotNull($monday);
        $this->assertSame([1, 0, 1], [$monday['bp'], $monday['gp'], $monday['tp']]); // boys/girls/total present
        $this->assertSame([0, 1, 1], [$monday['ba'], $monday['ga'], $monday['ta']]); // boys/girls/total absent
    }

    #[Test]
    public function the_monthly_summary_downloads_a_pdf()
    {
        [$offering] = $this->classWithPupil();

        $response = $this->actingAs($this->headmaster)
            ->get(route('attendance.summary', ['offering' => $offering, 'month' => '2026-05']));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    #[Test]
    public function the_summary_groups_by_mon_fri_calendar_week_not_seven_day_blocks()
    {
        [$offering] = $this->classWithPupil();

        // May 2025 starts on a Thursday — the case Carbon's weekOfMonth got wrong.
        $weeks = \App\Http\Controllers\NewInterfaceControllers\AttendanceController::monthTally(
            $offering->id, \Illuminate\Support\Carbon::parse('2025-05-01')
        );

        // Week 1 is the short first week (Thu 1, Fri 2); week 2 starts Monday the 5th.
        $this->assertCount(2, $weeks[1]);
        $this->assertSame(['01/05', '02/05'], [$weeks[1][0]['date'], $weeks[1][1]['date']]);
        $this->assertSame('05/05', $weeks[2][0]['date']);
        $this->assertCount(5, $weeks[2]);
    }
}
