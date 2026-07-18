<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\Offering;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginListTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function teacher_with_a_current_year_offering_appears_in_login_dropdown()
    {
        $offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id,
            'grade_id' => Grade::factory()->create(['name' => 'Grade 3'])->id,
        ]);
        DB::table('teacher_offering')->insert([
            'user_id' => $this->teacher->id,
            'offering_id' => $offering->id,
            'principal' => true,
        ]);

        $this->get('/login')->assertSee($this->teacher->first_name);
    }

    #[Test]
    public function subject_teacher_across_multiple_grades_appears_in_login_dropdown()
    {
        // e.g. the French teacher — not principal of any class, but teaches grades 3, 4, 5.
        $french = User::create(['first_name' => 'Aminata', 'last_name' => 'French', 'password' => bcrypt('x')]);
        $french->assignRole('teacher');

        foreach (['Grade 3', 'Grade 4', 'Grade 5'] as $name) {
            $offering = Offering::factory()->create([
                'schoolyear_id' => $this->schoolyear->id,
                'grade_id' => Grade::factory()->create(['name' => $name])->id,
            ]);
            DB::table('teacher_offering')->insert([
                'user_id' => $french->id,
                'offering_id' => $offering->id,
                'principal' => false,
            ]);
        }

        $this->get('/login')->assertSee('Aminata');
    }

    #[Test]
    public function teacher_with_no_current_offering_is_hidden_from_login()
    {
        // teacher exists but is not assigned to any offering in the current year.
        // Sentinel name so a random faker first name can't be a substring of a
        // visible user's name and trip assertDontSee (this list is not paginated).
        $this->teacher->update(['first_name' => 'Zqhiddenteacher']);

        $this->get('/login')->assertDontSee($this->teacher->first_name);
    }

    #[Test]
    public function admin_and_headmaster_always_appear_even_without_offerings()
    {
        $this->get('/login')
            ->assertSee($this->admin->first_name)
            ->assertSee($this->headmaster->first_name);
    }

    #[Test]
    public function students_never_appear_in_login_dropdown()
    {
        // Sentinel name (see above): guards against a faker first name that
        // happens to be a substring of a visible staff member's name.
        $this->student->update(['first_name' => 'Zqhiddenstudent']);

        $this->get('/login')->assertDontSee($this->student->first_name);
    }

    #[Test]
    public function assistant_coordinator_appears_without_offerings()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'assistant_coordinator']);

        $sub = User::create(['first_name' => 'Sulayman', 'last_name' => 'Janneh', 'password' => bcrypt('x')]);
        $sub->assignRole('assistant_coordinator');

        $this->get('/login')->assertSee('Sulayman');
    }
}
