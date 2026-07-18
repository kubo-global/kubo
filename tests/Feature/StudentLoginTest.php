<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\Offering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudentLoginTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_landing_asks_for_a_grade(): void
    {
        $grade = Grade::factory()->create(['name' => 'Grade 1']);
        Offering::factory()->create(['schoolyear_id' => $this->schoolyear->id, 'grade_id' => $grade->id]);

        $this->get(route('student-login.select-grade'))
            ->assertOk()
            ->assertSee('What grade are you in?')
            ->assertSee('Grade 1');
    }

    #[Test]
    public function a_grade_with_one_class_skips_straight_to_the_pupils(): void
    {
        $grade = Grade::factory()->create(['name' => 'Grade 2']);
        $offering = Offering::factory()->create(['schoolyear_id' => $this->schoolyear->id, 'grade_id' => $grade->id, 'name' => null]);

        $this->get(route('student-login.select-class', $grade))
            ->assertRedirect(route('student-login.select-student', $offering));
    }

    #[Test]
    public function a_grade_with_several_classes_shows_the_class_list(): void
    {
        $grade = Grade::factory()->create(['name' => 'Grade 3']);
        $a = Offering::factory()->create(['schoolyear_id' => $this->schoolyear->id, 'grade_id' => $grade->id, 'name' => 'A']);
        $b = Offering::factory()->create(['schoolyear_id' => $this->schoolyear->id, 'grade_id' => $grade->id, 'name' => 'B']);

        $this->get(route('student-login.select-class', $grade))
            ->assertOk()
            ->assertSee('Which class?')
            ->assertSee($a->displayName())
            ->assertSee($b->displayName());
    }

    // --- Which grades may log in (Settings → Academic) ---

    #[Test]
    public function a_grade_with_login_disabled_is_hidden_from_the_picker(): void
    {
        $shown = Grade::factory()->create(['name' => 'Grade 1', 'student_login_enabled' => true]);
        $hidden = Grade::factory()->create(['name' => 'Nursery 1', 'student_login_enabled' => false]);
        Offering::factory()->create(['schoolyear_id' => $this->schoolyear->id, 'grade_id' => $shown->id]);
        Offering::factory()->create(['schoolyear_id' => $this->schoolyear->id, 'grade_id' => $hidden->id]);

        $this->get(route('student-login.select-grade'))
            ->assertOk()
            ->assertSee('Grade 1')
            ->assertDontSee('Nursery 1');
    }

    #[Test]
    public function a_disabled_grade_cannot_be_reached_by_deep_link(): void
    {
        $grade = Grade::factory()->create(['name' => 'Nursery 2', 'student_login_enabled' => false]);
        $offering = Offering::factory()->create(['schoolyear_id' => $this->schoolyear->id, 'grade_id' => $grade->id, 'name' => 'A']);

        $this->get(route('student-login.select-class', $grade))
            ->assertRedirect(route('student-login.select-grade'));

        $this->get(route('student-login.select-student', $offering))
            ->assertRedirect(route('student-login.select-grade'));
    }

    #[Test]
    public function login_access_can_be_toggled_per_grade_from_settings(): void
    {
        $grade = Grade::factory()->create(['name' => 'Nursery 3', 'student_login_enabled' => true]);

        $this->actingAs($this->headmaster)
            ->post(route('settings.grade-login', $grade), ['enabled' => '0'])
            ->assertRedirect();
        $this->assertFalse($grade->fresh()->student_login_enabled);

        $this->actingAs($this->headmaster)
            ->post(route('settings.grade-login', $grade), ['enabled' => '1'])
            ->assertRedirect();
        $this->assertTrue($grade->fresh()->student_login_enabled);
    }
}
