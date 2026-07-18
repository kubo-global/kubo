<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Offering;
use App\Models\Schoolyear;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RolloverTest extends TestCase
{
    use RefreshDatabase;

    private Schoolyear $previousYear;
    private array $offerings = [];
    private array $students = [];

    public function setUp(): void
    {
        parent::setUp();

        // Create previous school year with terms, grades, offerings, and students
        $this->previousYear = Schoolyear::create([
            'name' => '2025 - 2026',
            'start' => '2025-09-01',
            'end' => '2026-08-31',
        ]);

        Term::create(['name' => 'Term 1', 'start' => '2025-09-01', 'end' => '2025-12-31', 'schoolyear_id' => $this->previousYear->id]);
        Term::create(['name' => 'Term 2', 'start' => '2026-01-15', 'end' => '2026-04-30', 'schoolyear_id' => $this->previousYear->id]);
        Term::create(['name' => 'Term 3', 'start' => '2026-05-15', 'end' => '2026-08-15', 'schoolyear_id' => $this->previousYear->id]);

        $grades = [
            Grade::factory()->create(['name' => 'Nursery 1']),
            Grade::factory()->create(['name' => 'Nursery 2']),
            Grade::factory()->create(['name' => 'Grade 1']),
        ];

        foreach ($grades as $grade) {
            $offering = Offering::factory()->create([
                'schoolyear_id' => $this->previousYear->id,
                'grade_id' => $grade->id,
            ]);
            $this->offerings[$grade->name] = $offering;

            // Assign teacher
            DB::table('teacher_offering')->insert([
                'user_id' => $this->teacher->id,
                'offering_id' => $offering->id,
                'principal' => true,
            ]);

            // Enroll 3 students per class
            for ($i = 0; $i < 3; $i++) {
                $student = User::create([
                    'first_name' => "Student{$i}",
                    'last_name' => $grade->name,
                    'password' => bcrypt('secret'),
                ]);
                $student->assignRole('student');
                Enrollment::create([
                    'user_id' => $student->id,
                    'offering_id' => $offering->id,
                ]);
                $this->students[] = $student;
            }
        }
    }

    // ===================== ACCESS CONTROL =====================

    #[Test]
    public function test_admin_can_access_rollover_page()
    {
        $this->actingAs($this->systemAdmin)
            ->get(route('rollover.index'))
            ->assertOk()
            ->assertSee('Create a new school year');
    }

    #[Test]
    public function test_teacher_cannot_access_rollover_page()
    {
        $teacherOnly = User::create([
            'first_name' => 'Just',
            'last_name' => 'Teacher',
            'password' => bcrypt('secret'),
        ]);
        $teacherOnly->assignRole('teacher');

        $this->actingAs($teacherOnly)
            ->get(route('rollover.index'))
            ->assertForbidden();
    }

    #[Test]
    public function test_student_cannot_access_rollover_page()
    {
        $this->actingAs($this->student)
            ->get(route('rollover.index'))
            ->assertForbidden();
    }

    // ===================== STEP 1: CREATE SCHOOL YEAR =====================

    #[Test]
    public function rollover_carries_the_nat_config_forward_when_copying()
    {
        // Give the previous year a NAT config (Grade 1 sits it, 2 subjects).
        $school = \App\Models\School::factory()->create();
        $grade = \App\Models\Grade::where('name', 'Grade 1')->first();
        $config = \App\Models\NatConfig::create([
            'school_id' => $school->id,
            'schoolyear_id' => $this->previousYear->id,
            'enabled' => true,
        ]);
        foreach (['English language', 'Mathematics'] as $i => $name) {
            $config->subjects()->create([
                'grade_id' => $grade->id,
                'subject_id' => \App\Models\Subject::factory()->create(['name' => $name])->id,
                'max_score' => 100,
                'display_order' => $i,
            ]);
        }

        $this->actingAs($this->systemAdmin)
            ->post(route('rollover.store'), [
                'name' => '2026 - 2027',
                'start' => '2026-09-01', 'end' => '2027-08-31',
                'term1_start' => '2026-09-01', 'term1_end' => '2026-12-31',
                'term2_start' => '2027-01-15', 'term2_end' => '2027-04-30',
                'term3_start' => '2027-05-15', 'term3_end' => '2027-08-15',
                'copy_from' => $this->previousYear->id,
            ])
            ->assertRedirect();

        $newYear = Schoolyear::where('name', '2026 - 2027')->first();
        $copied = \App\Models\NatConfig::for($school, $newYear);

        $this->assertNotNull($copied, 'NAT config was not carried forward');
        $this->assertTrue($copied->gradeSits($grade->id));
        $this->assertCount(2, $copied->subjectsForGrade($grade->id));
    }

    #[Test]
    public function test_create_school_year_with_terms_and_offerings()
    {
        $gradeCount = Grade::count();

        $this->actingAs($this->systemAdmin)
            ->post(route('rollover.store'), [
                'name' => '2026 - 2027',
                'start' => '2026-09-01',
                'end' => '2027-08-31',
                'term1_start' => '2026-09-01',
                'term1_end' => '2026-12-31',
                'term2_start' => '2027-01-15',
                'term2_end' => '2027-04-30',
                'term3_start' => '2027-05-15',
                'term3_end' => '2027-08-15',
                'copy_from' => null,
            ])
            ->assertRedirect();

        $newYear = Schoolyear::where('name', '2026 - 2027')->first();
        $this->assertNotNull($newYear);
        $this->assertEquals(3, $newYear->terms()->count());
        $this->assertEquals($gradeCount, Offering::where('schoolyear_id', $newYear->id)->count());
    }

    #[Test]
    public function test_create_school_year_copies_teachers_from_previous()
    {
        $this->actingAs($this->systemAdmin)
            ->post(route('rollover.store'), [
                'name' => '2026 - 2027',
                'start' => '2026-09-01',
                'end' => '2027-08-31',
                'term1_start' => '2026-09-01',
                'term1_end' => '2026-12-31',
                'term2_start' => '2027-01-15',
                'term2_end' => '2027-04-30',
                'term3_start' => '2027-05-15',
                'term3_end' => '2027-08-15',
                'copy_from' => $this->previousYear->id,
            ])
            ->assertRedirect();

        $newYear = Schoolyear::where('name', '2026 - 2027')->first();
        $newOfferings = Offering::where('schoolyear_id', $newYear->id)->get();

        // Teacher should be copied to new offerings
        foreach ($newOfferings as $offering) {
            $hasTeacher = DB::table('teacher_offering')
                ->where('offering_id', $offering->id)
                ->where('user_id', $this->teacher->id)
                ->exists();
            $this->assertTrue($hasTeacher, "Teacher not copied to offering {$offering->id}");
        }
    }

    #[Test]
    public function test_create_school_year_validates_required_fields()
    {
        $this->actingAs($this->systemAdmin)
            ->post(route('rollover.store'), [])
            ->assertSessionHasErrors(['name', 'start', 'end', 'term1_start', 'term1_end']);
    }

    #[Test]
    public function test_create_school_year_validates_end_after_start()
    {
        $this->actingAs($this->systemAdmin)
            ->post(route('rollover.store'), [
                'name' => 'Bad Year',
                'start' => '2027-09-01',
                'end' => '2026-08-31', // before start
                'term1_start' => '2026-09-01',
                'term1_end' => '2026-12-31',
                'term2_start' => '2027-01-15',
                'term2_end' => '2027-04-30',
                'term3_start' => '2027-05-15',
                'term3_end' => '2027-08-15',
            ])
            ->assertSessionHasErrors('end');
    }

    // ===================== STEP 2: PROMOTE STUDENTS =====================

    #[Test]
    public function test_promote_page_shows_previous_year_students()
    {
        // Create new year first
        $newYear = $this->createNewSchoolYear();

        $this->actingAs($this->systemAdmin)
            ->get(route('rollover.promote', $newYear))
            ->assertOk()
            ->assertSee('Student0')
            ->assertSee('Nursery 1');
    }

    #[Test]
    public function test_promote_students_creates_enrollments()
    {
        $newYear = $this->createNewSchoolYear();
        $newOfferings = Offering::where('schoolyear_id', $newYear->id)->get()->keyBy('grade_id');

        // Promote all Nursery 1 students to Nursery 2
        $nursery1Students = Enrollment::where('offering_id', $this->offerings['Nursery 1']->id)
            ->pluck('user_id');

        $nursery2GradeId = Grade::where('name', 'Nursery 2')->first()->id;

        $nursery2OfferingId = $newOfferings->get($nursery2GradeId)->id;

        $actions = [];
        $promoteOfferings = [];
        foreach ($nursery1Students as $studentId) {
            $actions[$studentId] = 'promote';
            $promoteOfferings[$studentId] = $nursery2OfferingId;
        }

        $this->actingAs($this->systemAdmin)
            ->post(route('rollover.process-promotions', $newYear), [
                'action' => $actions,
                'promote_offering' => $promoteOfferings,
            ])
            ->assertRedirect();

        // Verify enrollments created
        $nursery2Offering = $newOfferings->get($nursery2GradeId);
        foreach ($nursery1Students as $studentId) {
            $this->assertDatabaseHas('enrollments', [
                'user_id' => $studentId,
                'offering_id' => $nursery2Offering->id,
            ]);
        }
    }

    #[Test]
    public function test_repeat_student_stays_in_same_grade()
    {
        $newYear = $this->createNewSchoolYear();
        $student = $this->students[0]; // First Nursery 1 student
        $nursery1GradeId = Grade::where('name', 'Nursery 1')->first()->id;

        $newNursery1 = Offering::where('schoolyear_id', $newYear->id)
            ->where('grade_id', $nursery1GradeId)
            ->first();

        $this->actingAs($this->systemAdmin)
            ->post(route('rollover.process-promotions', $newYear), [
                'action' => [$student->id => 'repeat'],
                'repeat_offering' => [$student->id => $newNursery1->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'offering_id' => $newNursery1->id,
        ]);
    }

    #[Test]
    public function test_left_school_archives_student()
    {
        $newYear = $this->createNewSchoolYear();
        $student = $this->students[0];

        $this->assertFalse((bool) $student->archived);

        $this->actingAs($this->systemAdmin)
            ->post(route('rollover.process-promotions', $newYear), [
                'action' => [$student->id => 'left'],
                'target_grade' => [],
                'current_grade' => [],
            ])
            ->assertRedirect();

        $student->refresh();
        $this->assertTrue((bool) $student->archived);

        // No new enrollment created
        $newEnrollments = Enrollment::whereHas('offering', fn ($q) => $q->where('schoolyear_id', $newYear->id))
            ->where('user_id', $student->id)
            ->count();
        $this->assertEquals(0, $newEnrollments);
    }

    #[Test]
    public function test_graduated_student_archived()
    {
        $newYear = $this->createNewSchoolYear();
        $student = $this->students[6]; // First Grade 1 student (highest grade in test data)

        $this->actingAs($this->systemAdmin)
            ->post(route('rollover.process-promotions', $newYear), [
                'action' => [$student->id => 'graduated'],
                'target_grade' => [],
                'current_grade' => [],
            ])
            ->assertRedirect();

        $student->refresh();
        $this->assertTrue((bool) $student->archived);
    }

    #[Test]
    public function test_promote_is_idempotent()
    {
        $newYear = $this->createNewSchoolYear();
        $student = $this->students[0];
        $nursery2GradeId = Grade::where('name', 'Nursery 2')->first()->id;

        $nursery2OfferingId = Offering::where('schoolyear_id', $newYear->id)
            ->where('grade_id', $nursery2GradeId)
            ->value('id');

        $payload = [
            'action' => [$student->id => 'promote'],
            'promote_offering' => [$student->id => $nursery2OfferingId],
        ];

        // Run twice
        $this->actingAs($this->systemAdmin)->post(route('rollover.process-promotions', $newYear), $payload);
        $this->actingAs($this->systemAdmin)->post(route('rollover.process-promotions', $newYear), $payload);

        // Should still have exactly 1 enrollment in new year
        $count = Enrollment::whereHas('offering', fn ($q) => $q->where('schoolyear_id', $newYear->id))
            ->where('user_id', $student->id)
            ->count();
        $this->assertEquals(1, $count);
    }

    // ===================== STEP 3: NEW STUDENTS =====================

    #[Test]
    public function test_new_students_page_loads()
    {
        $newYear = $this->createNewSchoolYear();

        $this->actingAs($this->systemAdmin)
            ->get(route('rollover.new-students', $newYear))
            ->assertOk()
            ->assertSee('Enroll new students');
    }

    #[Test]
    public function test_enroll_new_student()
    {
        $newYear = $this->createNewSchoolYear();
        $offering = Offering::where('schoolyear_id', $newYear->id)->first();

        $this->actingAs($this->systemAdmin)
            ->post(route('rollover.enroll-new', $newYear), [
                'first_name' => 'Fatou',
                'last_name' => 'Jallow',
                'offering_id' => $offering->id,
                'gender' => 'female',
                'date_of_birth' => '2020-03-15',
            ])
            ->assertRedirect();

        $student = User::where('first_name', 'Fatou')->where('last_name', 'Jallow')->first();
        $this->assertNotNull($student);
        $this->assertTrue($student->hasRole('student'));
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'offering_id' => $offering->id,
        ]);
    }

    #[Test]
    public function test_enroll_new_student_validates_name()
    {
        $newYear = $this->createNewSchoolYear();
        $offering = Offering::where('schoolyear_id', $newYear->id)->first();

        $this->actingAs($this->systemAdmin)
            ->post(route('rollover.enroll-new', $newYear), [
                'first_name' => '',
                'last_name' => '',
                'offering_id' => $offering->id,
            ])
            ->assertSessionHasErrors(['first_name', 'last_name']);
    }

    // ===================== EDGE CASES =====================

    #[Test]
    public function test_promote_with_no_previous_year_redirects()
    {
        // Create a year with no previous
        $firstYear = Schoolyear::create([
            'name' => 'First Ever',
            'start' => '2010-01-01',
            'end' => '2010-12-31',
        ]);

        // Delete all other years
        Schoolyear::where('id', '!=', $firstYear->id)->delete();

        $this->actingAs($this->systemAdmin)
            ->get(route('rollover.promote', $firstYear))
            ->assertRedirect(route('rollover.index'));
    }

    #[Test]
    public function test_historical_enrollments_preserved_after_rollover()
    {
        $newYear = $this->createNewSchoolYear();
        $student = $this->students[0];
        $nursery2GradeId = Grade::where('name', 'Nursery 2')->first()->id;

        $nursery2OfferingId = Offering::where('schoolyear_id', $newYear->id)
            ->where('grade_id', $nursery2GradeId)
            ->value('id');

        // Promote
        $this->actingAs($this->systemAdmin)
            ->post(route('rollover.process-promotions', $newYear), [
                'action' => [$student->id => 'promote'],
                'promote_offering' => [$student->id => $nursery2OfferingId],
            ]);

        // Old enrollment still exists
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'offering_id' => $this->offerings['Nursery 1']->id,
        ]);

        // New enrollment also exists
        $newOffering = Offering::where('schoolyear_id', $newYear->id)
            ->where('grade_id', $nursery2GradeId)
            ->first();
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'offering_id' => $newOffering->id,
        ]);

        // Student has 2 enrollments total
        $this->assertEquals(2, Enrollment::where('user_id', $student->id)->count());
    }

    // ===================== HELPERS =====================

    private function createNewSchoolYear(): Schoolyear
    {
        $response = $this->actingAs($this->systemAdmin)
            ->post(route('rollover.store'), [
                'name' => '2026 - 2027',
                'start' => '2026-09-01',
                'end' => '2027-08-31',
                'term1_start' => '2026-09-01',
                'term1_end' => '2026-12-31',
                'term2_start' => '2027-01-15',
                'term2_end' => '2027-04-30',
                'term3_start' => '2027-05-15',
                'term3_end' => '2027-08-15',
                'copy_from' => $this->previousYear->id,
            ]);

        return Schoolyear::where('name', '2026 - 2027')->first();
    }
}
