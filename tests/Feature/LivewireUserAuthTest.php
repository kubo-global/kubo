<?php

namespace Tests\Feature;

use App\Livewire\LivewireUser;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LivewireUser is rendered on /profile (auth-only), so its actions are
 * POST-invokable by any logged-in user — including a student. Every
 * state-changing method must reject non-staff, not rely on page middleware.
 */
class LivewireUserAuthTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_student_cannot_update_a_profile(): void
    {
        $this->actingAs($this->student);

        Livewire::test(LivewireUser::class, ['user' => $this->student, 'method' => ''])
            ->set('firstName', 'Hacked')
            ->set('lastName', 'Name')
            ->call('updateProfile')
            ->assertForbidden();

        $this->assertNotSame('Hacked', $this->student->fresh()->first_name);
    }

    #[Test]
    public function a_student_cannot_self_enrol_or_enter_edit_mode(): void
    {
        $this->actingAs($this->student);

        Livewire::test(LivewireUser::class, ['user' => $this->student, 'method' => ''])
            ->call('makeProfileEditable')
            ->assertForbidden();

        Livewire::test(LivewireUser::class, ['user' => $this->student, 'method' => ''])
            ->call('enrollStudent')
            ->assertForbidden();
    }

    #[Test]
    public function staff_can_still_update_a_profile(): void
    {
        $pupil = Student::factory()->create(['first_name' => 'Old', 'last_name' => 'Name']);

        $this->actingAs($this->headmaster);

        Livewire::test(LivewireUser::class, ['user' => $pupil, 'method' => ''])
            ->set('firstName', 'Updated')
            ->set('lastName', 'Pupil')
            ->call('updateProfile')
            ->assertHasNoErrors();

        $this->assertSame('Updated', $pupil->fresh()->first_name);
    }
}
