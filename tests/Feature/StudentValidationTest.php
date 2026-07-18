<?php

namespace Tests\Feature;

use App\Livewire\LivewireUser;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Offering;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudentValidationTest extends TestCase
{
    use RefreshDatabase;

    private Offering $offering;

    public function setUp(): void
    {
        parent::setUp();

        $this->offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id' => Grade::factory()->create(['name' => 'Grade 1'])->id,
        ]);
    }

    #[Test]
    public function updating_an_existing_student_keeps_legacy_nulls_acceptable()
    {
        // Mirror the prod data shape: existing student with most fields null.
        $student = Student::create(['first_name' => 'Sadibou', 'last_name' => 'Tamba', 'password' => bcrypt('x')]);
        $student->assignRole('student');
        $student->profile()->create([]); // empty profile, mirroring incomplete records

        $this->actingAs($this->teacher);

        Livewire::test(LivewireUser::class, ['user' => $student, 'method' => ''])
            ->set('firstName', 'Sadibou')
            ->set('lastName', 'Tamba')
            // gender, birthDate, tribe, phone, address all left blank — matches reality
            ->call('updateProfile')
            ->assertHasNoErrors();
    }

    #[Test]
    public function updating_with_blank_first_name_fails()
    {
        $student = Student::create(['first_name' => 'Sadibou', 'last_name' => 'Tamba', 'password' => bcrypt('x')]);
        $student->assignRole('student');
        $student->profile()->create([]);

        $this->actingAs($this->teacher);

        Livewire::test(LivewireUser::class, ['user' => $student, 'method' => ''])
            ->set('firstName', '')
            ->call('updateProfile')
            ->assertHasErrors(['firstName' => 'required']);
    }

    #[Test]
    public function updating_with_a_garbage_birth_date_fails()
    {
        $student = Student::create(['first_name' => 'Sadibou', 'last_name' => 'Tamba', 'password' => bcrypt('x')]);
        $student->assignRole('student');
        $student->profile()->create([]);

        $this->actingAs($this->teacher);

        Livewire::test(LivewireUser::class, ['user' => $student, 'method' => ''])
            ->set('birthDate', 'banana')
            ->call('updateProfile')
            ->assertHasErrors(['birthDate' => 'date']);
    }

    #[Test]
    public function updating_with_a_future_birth_date_fails()
    {
        $student = Student::create(['first_name' => 'Sadibou', 'last_name' => 'Tamba', 'password' => bcrypt('x')]);
        $student->assignRole('student');
        $student->profile()->create([]);

        $this->actingAs($this->teacher);

        Livewire::test(LivewireUser::class, ['user' => $student, 'method' => ''])
            ->set('birthDate', '3000-01-01')
            ->call('updateProfile')
            ->assertHasErrors(['birthDate' => 'before']);
    }

    #[Test]
    public function updating_with_unknown_gender_value_fails()
    {
        $student = Student::create(['first_name' => 'Sadibou', 'last_name' => 'Tamba', 'password' => bcrypt('x')]);
        $student->assignRole('student');
        $student->profile()->create([]);

        $this->actingAs($this->teacher);

        Livewire::test(LivewireUser::class, ['user' => $student, 'method' => ''])
            ->set('gender', 'banana')
            ->call('updateProfile')
            ->assertHasErrors(['gender' => 'in']);
    }

    #[Test]
    public function enrolling_a_new_student_requires_offering_gender_and_birth_date()
    {
        $this->actingAs($this->admin);

        Livewire::test(LivewireUser::class, ['user' => null, 'method' => 'newStudent'])
            ->set('firstName', 'New')
            ->set('lastName', 'Student')
            ->set('selectedOffering', null)
            ->set('birthDate', '')
            ->call('enrollStudent')
            ->assertHasErrors([
                'selectedOffering' => 'required',
                'birthDate' => 'required',
            ]);

        $this->assertDatabaseMissing('users', ['first_name' => 'New', 'last_name' => 'Student']);
    }

    #[Test]
    public function enrolling_a_new_student_with_complete_data_succeeds()
    {
        $this->actingAs($this->admin);

        Livewire::test(LivewireUser::class, ['user' => null, 'method' => 'newStudent'])
            ->set('firstName', 'New')
            ->set('lastName', 'Student')
            ->set('gender', 'F')
            ->set('birthDate', '2018-03-15')
            ->set('selectedOffering', $this->offering->id)
            ->call('enrollStudent')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['first_name' => 'New', 'last_name' => 'Student']);
        $this->assertDatabaseHas('enrollments', ['offering_id' => $this->offering->id]);
    }
}
