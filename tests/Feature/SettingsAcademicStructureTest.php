<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\Offering;
use App\Models\Schoolyear;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ports the legacy admin GradesTest/SubjectsTest/OfferingsTest/TeacherClassesTest
 * coverage onto the new Settings interface. All routes are gated by
 * auth + permission:manage settings; the headmaster role holds it.
 */
class SettingsAcademicStructureTest extends TestCase
{
    use RefreshDatabase;

    // ---- Grades ----

    #[Test]
    public function a_grade_can_be_created(): void
    {
        $this->actingAs($this->headmaster)
            ->post(route('settings.store-grade'), ['name' => 'Grade 7'])
            ->assertRedirect();

        $this->assertDatabaseHas('grades', ['name' => 'Grade 7']);
    }

    #[Test]
    public function a_grade_can_be_deleted(): void
    {
        $grade = Grade::factory()->create(['name' => 'Grade 8']);

        $this->actingAs($this->headmaster)
            ->delete(route('settings.destroy-grade', $grade))
            ->assertRedirect();

        $this->assertDatabaseMissing('grades', ['id' => $grade->id]);
    }

    // ---- Subjects ----

    #[Test]
    public function a_subject_can_be_created(): void
    {
        $this->actingAs($this->headmaster)
            ->post(route('settings.store-subject'), ['name' => 'Mathematics'])
            ->assertRedirect();

        $this->assertDatabaseHas('subjects', ['name' => 'Mathematics']);
    }

    #[Test]
    public function a_subject_can_be_deleted(): void
    {
        $subject = Subject::factory()->create(['name' => 'History']);

        $this->actingAs($this->headmaster)
            ->delete(route('settings.destroy-subject', $subject))
            ->assertRedirect();

        $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
    }

    // ---- Offerings (class sections) ----

    #[Test]
    public function an_offering_can_be_added_for_a_grade(): void
    {
        // addOffering uses Schoolyear::current() ?? latest(); make a
        // current-dated year so the new offering lands there deterministically.
        $year = Schoolyear::create([
            'name' => '2026-2027', 'start' => now()->subMonth(), 'end' => now()->addMonths(9),
        ]);
        $grade = Grade::factory()->create(['name' => 'Grade 1']);

        $this->actingAs($this->headmaster)
            ->post(route('settings.add-offering'), ['grade_id' => $grade->id, 'name' => 'Grade 1 B'])
            ->assertRedirect();

        $this->assertDatabaseHas('offerings', [
            'schoolyear_id' => $year->id,
            'grade_id' => $grade->id,
            'name' => 'Grade 1 B',
        ]);
    }

    #[Test]
    public function an_offering_can_be_renamed(): void
    {
        $offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id' => Grade::factory()->create()->id,
            'name' => 'Old Name',
        ]);

        $this->actingAs($this->headmaster)
            ->post(route('settings.rename-offering', $offering), ['name' => 'New Name'])
            ->assertRedirect();

        $this->assertDatabaseHas('offerings', ['id' => $offering->id, 'name' => 'New Name']);
    }

    #[Test]
    public function an_offering_without_students_or_teachers_can_be_deleted(): void
    {
        $offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id' => Grade::factory()->create()->id,
        ]);

        $this->actingAs($this->headmaster)
            ->delete(route('settings.delete-offering', $offering))
            ->assertRedirect();

        $this->assertDatabaseMissing('offerings', ['id' => $offering->id]);
    }

    // ---- Teacher assignments ----

    #[Test]
    public function a_teacher_can_be_assigned_to_an_offering(): void
    {
        $offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id' => Grade::factory()->create()->id,
        ]);

        $this->actingAs($this->headmaster)
            ->post(route('settings.assign-teacher'), [
                'user_id' => $this->teacher->id,
                'offering_id' => $offering->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('teacher_offering', [
            'user_id' => $this->teacher->id,
            'offering_id' => $offering->id,
        ]);
    }

    #[Test]
    public function a_teacher_can_be_removed_from_an_offering(): void
    {
        $offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id' => Grade::factory()->create()->id,
        ]);
        DB::table('teacher_offering')->insert([
            'user_id' => $this->teacher->id,
            'offering_id' => $offering->id,
            'principal' => false,
        ]);

        $this->actingAs($this->headmaster)
            ->post(route('settings.remove-teacher'), [
                'user_id' => $this->teacher->id,
                'offering_id' => $offering->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('teacher_offering', [
            'user_id' => $this->teacher->id,
            'offering_id' => $offering->id,
        ]);
    }

    // ---- Subject-term mapping ----

    #[Test]
    public function a_subject_can_be_mapped_to_a_term_for_an_offering(): void
    {
        $offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id' => Grade::factory()->create()->id,
        ]);
        $subject = Subject::factory()->create();

        $this->actingAs($this->headmaster)
            ->post(route('settings.add-class-subject'), [
                'subject_id' => $subject->id,
                'term_id' => $this->term->id,
                'offering_id' => $offering->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('subject_term_offering', [
            'subject_id' => $subject->id,
            'term_id' => $this->term->id,
            'offering_id' => $offering->id,
        ]);
    }

    #[Test]
    public function a_subject_can_be_unmapped_from_a_term(): void
    {
        $offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id' => Grade::factory()->create()->id,
        ]);
        $subject = Subject::factory()->create();
        DB::table('subject_term_offering')->insert([
            'subject_id' => $subject->id,
            'term_id' => $this->term->id,
            'offering_id' => $offering->id,
        ]);

        $this->actingAs($this->headmaster)
            ->post(route('settings.remove-class-subject'), [
                'subject_id' => $subject->id,
                'term_id' => $this->term->id,
                'offering_id' => $offering->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('subject_term_offering', [
            'subject_id' => $subject->id,
            'term_id' => $this->term->id,
            'offering_id' => $offering->id,
        ]);
    }

    // ---- Permission guard ----

    #[Test]
    public function a_plain_teacher_cannot_manage_academic_structure(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('settings.store-grade'), ['name' => 'Grade 99'])
            ->assertForbidden();

        $this->assertDatabaseMissing('grades', ['name' => 'Grade 99']);
    }
}
