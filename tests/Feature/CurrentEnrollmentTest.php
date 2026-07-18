<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Offering;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression coverage for the production-data shape that broke
 * User::currentEnrollment() before we switched it to resolve via
 * Schoolyear::current() instead of created_at ordering.
 */
class CurrentEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function current_grade_uses_current_schoolyear_even_when_created_at_points_at_an_older_enrollment()
    {
        Carbon::setTestNow('2026-05-06');

        $oldYear = Schoolyear::create(['name' => '2018 - 2019', 'start' => '2018-09-01', 'end' => '2019-08-31']);
        $currentYear = Schoolyear::create(['name' => '2025 - 2026', 'start' => '2025-09-01', 'end' => '2026-08-31']);

        $gradeOne = Grade::factory()->create(['name' => 'Grade 1']);
        $gradeNine = Grade::factory()->create(['name' => 'Grade 9']);

        $oldOffering = Offering::factory()->create(['schoolyear_id' => $oldYear->id, 'grade_id' => $gradeOne->id]);
        $currentOffering = Offering::factory()->create(['schoolyear_id' => $currentYear->id, 'grade_id' => $gradeNine->id]);

        $student = User::create(['first_name' => 'Nyima', 'last_name' => 'Test', 'password' => bcrypt('x')]);
        $student->assignRole('student');

        // Old enrollment has a populated created_at (the trap that bit prod data),
        // current enrollment has a NULL created_at — sorts last under DESC.
        $oldEnrollment = Enrollment::create(['user_id' => $student->id, 'offering_id' => $oldOffering->id]);
        Enrollment::where('id', $oldEnrollment->id)->update(['created_at' => '2018-09-20 16:08:32']);

        $currentEnrollment = Enrollment::create(['user_id' => $student->id, 'offering_id' => $currentOffering->id]);
        Enrollment::where('id', $currentEnrollment->id)->update(['created_at' => null]);

        $student->refresh();

        $this->assertSame($gradeNine->id, $student->currentGradeId(),
            'currentGradeId should resolve via current schoolyear, not by created_at DESC.');
        $this->assertSame($currentOffering->id, $student->currentOfferingId());
    }

    #[Test]
    public function current_grade_is_null_when_student_has_no_enrollment_in_the_current_year_but_other_years_exist()
    {
        Carbon::setTestNow('2026-05-06');

        $oldYear = Schoolyear::create(['name' => '2020 - 2021', 'start' => '2020-09-01', 'end' => '2021-08-31']);
        $currentYear = Schoolyear::create(['name' => '2025 - 2026', 'start' => '2025-09-01', 'end' => '2026-08-31']);

        $oldGrade = Grade::factory()->create(['name' => 'Grade 3']);
        $oldOffering = Offering::factory()->create(['schoolyear_id' => $oldYear->id, 'grade_id' => $oldGrade->id]);

        $alum = User::create(['first_name' => 'Bubacarr', 'last_name' => 'Alum', 'password' => bcrypt('x')]);
        $alum->assignRole('student');
        Enrollment::create(['user_id' => $alum->id, 'offering_id' => $oldOffering->id]);

        // Schoolyear::current() resolves to $currentYear; the alum has no
        // enrollment there, so the result is null instead of falsely
        // surfacing an old grade.
        $this->assertNull($alum->refresh()->currentGradeId());
        $this->assertNull($alum->currentOfferingId());
    }
}
