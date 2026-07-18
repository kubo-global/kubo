<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\NatConfig;
use App\Models\Offering;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    /** Simulate a fresh install: roles are seeded (base setUp) but no school/users/admins exist. */
    private function freshInstance(): void
    {
        DB::table('model_has_roles')->delete();
        User::query()->delete();
        School::query()->delete();
    }

    #[Test]
    public function a_fresh_instance_funnels_every_request_to_the_installer(): void
    {
        $this->freshInstance();

        $this->get('/login')->assertRedirect(route('install.index'));
        $this->get(route('install.index'))->assertOk()->assertSee('Start setup');       // welcome intro
        $this->get(route('install.school'))->assertOk()->assertSee('School name');
    }

    #[Test]
    public function a_set_up_instance_redirects_away_from_the_installer(): void
    {
        // The base setUp already created a headmaster, so this instance is "set up".
        $this->get(route('install.school'))->assertRedirect('/');
    }

    #[Test]
    public function completing_the_wizard_sets_up_a_gambian_school_and_signs_in(): void
    {
        $this->freshInstance();

        $this->post(route('install.school.store'), [
            'name' => 'The Swallow Center',
            'country' => 'GM',
            'motto' => 'Learn and grow',
        ])->assertRedirect(route('install.structure'));

        $this->post(route('install.structure.store'), [
            'year_name' => '2025-2026',
            'start' => '2025-09-01',
            'end' => '2026-08-31',
        ])->assertRedirect(route('install.classes'));

        // Accept the preset grades and subjects as-is.
        $this->post(route('install.classes.store'), [])->assertRedirect(route('install.features'));

        // Keep the default modules.
        $this->post(route('install.features.store'), [])->assertRedirect(route('install.admin'));

        $this->post(route('install.admin.store'), [
            'first_name' => 'Awa',
            'last_name' => 'Touray',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect(route('install.review'));

        $this->post(route('install.complete'))->assertRedirect('/');
        $this->assertAuthenticated();

        $school = School::first();
        $this->assertNotNull($school);
        $this->assertSame('The Swallow Center', $school->name);

        $year = Schoolyear::where('name', '2025-2026')->first();
        $this->assertNotNull($year);
        $this->assertSame(3, Term::where('schoolyear_id', $year->id)->count());

        // Gambian preset: Nursery 1-3 + Grade 1-6, WAEC subjects, an offering per grade, NAT.
        $this->assertSame(9, Grade::count());
        $this->assertGreaterThanOrEqual(10, Subject::count());
        $this->assertSame(9, Offering::where('schoolyear_id', $year->id)->count());
        $this->assertTrue(NatConfig::where('schoolyear_id', $year->id)->exists());

        // Gambian grade key (grade 1 = best), not the A-F fallback.
        $this->assertSame(
            ['1', '4', '5', '6', '8', '9'],
            \App\Models\GradingScale::where('school_id', $school->id)->whereNull('purpose')
                ->orderBy('display_order')->pluck('label')->all()
        );

        $admin = User::where('first_name', 'Awa')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('admin'));   // the first account runs the install, then configures the rest

        // Subjects attached to a class for the term (what the scorebook reads).
        $offering = Offering::where('schoolyear_id', $year->id)->first();
        $term = Term::where('schoolyear_id', $year->id)->first();
        $this->assertGreaterThan(0, $offering->subjects($term->id)->count());
    }

    #[Test]
    public function a_non_preset_country_skips_the_gambian_curriculum(): void
    {
        $this->freshInstance();

        $this->post(route('install.school.store'), ['name' => 'Springfield Elementary', 'country' => 'OTHER']);
        $this->post(route('install.structure.store'), [
            'year_name' => '2025-2026', 'start' => '2025-09-01', 'end' => '2026-08-31',
        ]);
        $this->post(route('install.classes.store'), ['manual_grades' => "Class A\nClass B"]);
        $this->post(route('install.admin.store'), [
            'first_name' => 'Seymour', 'last_name' => 'Skinner',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ]);
        $this->post(route('install.complete'))->assertRedirect('/');

        $this->assertSame(2, Grade::count());              // only the two typed-in classes
        $this->assertSame(0, Subject::count());            // no Gambian subjects pushed
        $this->assertFalse(NatConfig::query()->exists());  // no NAT
    }

    #[Test]
    public function the_school_picks_its_own_subjects_including_custom_ones(): void
    {
        $this->freshInstance();

        $this->post(route('install.school.store'), ['name' => 'Sifoe Lower Basic', 'country' => 'GM']);
        $this->post(route('install.structure.store'), [
            'year_name' => '2025-2026', 'start' => '2025-09-01', 'end' => '2026-08-31',
        ]);
        $this->post(route('install.classes.store'), [
            'subjects_present' => '1',
            'subjects' => ['English language', 'Mathematics'],
            'custom_subjects' => "Robotics\nSwimming",
        ]);
        $this->post(route('install.admin.store'), [
            'first_name' => 'Awa', 'last_name' => 'Touray',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ]);
        $this->post(route('install.complete'))->assertRedirect('/');

        $names = Subject::pluck('name')->all();
        $this->assertEqualsCanonicalizing(['English language', 'Mathematics', 'Robotics', 'Swimming'], $names);
        $this->assertNotContains('French', $names);   // an unticked optional subject is left out
        $this->assertNotContains('Health', $names);
    }

    #[Test]
    public function the_school_can_edit_the_term_dates(): void
    {
        $this->freshInstance();

        $this->post(route('install.school.store'), ['name' => 'Sifoe Lower Basic', 'country' => 'GM']);
        $this->post(route('install.structure.store'), [
            'year_name' => '2025-2026', 'start' => '2025-09-01', 'end' => '2026-08-31',
            'terms' => [
                ['name' => 'First Term', 'start' => '2025-09-01', 'end' => '2025-12-20'],
                ['name' => 'Second Term', 'start' => '2026-01-05', 'end' => '2026-04-10'],
                ['name' => 'Third Term', 'start' => '2026-04-20', 'end' => '2026-07-25'],
            ],
        ]);
        $this->post(route('install.classes.store'), []);
        $this->post(route('install.admin.store'), [
            'first_name' => 'Awa', 'last_name' => 'Touray',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ]);
        $this->post(route('install.complete'))->assertRedirect('/');

        $year = Schoolyear::where('name', '2025-2026')->first();
        $terms = Term::where('schoolyear_id', $year->id)->orderBy('start')->get();
        $this->assertSame(['First Term', 'Second Term', 'Third Term'], $terms->pluck('name')->all());
        $this->assertStringStartsWith('2025-12-20', (string) $terms->first()->end);
    }

    #[Test]
    public function the_school_picks_its_grades_and_class_counts(): void
    {
        $this->freshInstance();

        $this->post(route('install.school.store'), ['name' => 'Sifoe Lower Basic', 'country' => 'GM']);
        $this->post(route('install.structure.store'), [
            'year_name' => '2025-2026', 'start' => '2025-09-01', 'end' => '2026-08-31',
        ]);
        $this->post(route('install.classes.store'), [
            'grades' => ['Grade 1', 'Grade 2'],                 // only these two grades
            'classes' => ['Grade 1' => 2, 'Grade 2' => 1],      // Grade 1 runs two classes
        ]);
        $this->post(route('install.admin.store'), [
            'first_name' => 'Awa', 'last_name' => 'Touray',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ]);
        $this->post(route('install.complete'))->assertRedirect('/');

        $this->assertSame(2, Grade::count());                   // only the two ticked grades
        $year = Schoolyear::where('name', '2025-2026')->first();
        $this->assertSame(3, Offering::where('schoolyear_id', $year->id)->count()); // Grade 1 A+B, Grade 2
    }

    #[Test]
    public function a_school_can_leave_a_module_off(): void
    {
        $this->freshInstance();

        $this->post(route('install.school.store'), ['name' => 'Kanilai Lower Basic', 'country' => 'GM']);
        $this->post(route('install.structure.store'), [
            'year_name' => '2025-2026', 'start' => '2025-09-01', 'end' => '2026-08-31',
        ]);
        $this->post(route('install.classes.store'), []);
        // A government school keeps Progress but leaves Health off.
        $this->post(route('install.features.store'), ['modules' => ['progress']]);
        $this->post(route('install.admin.store'), [
            'first_name' => 'Awa', 'last_name' => 'Touray',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ]);
        $this->post(route('install.complete'))->assertRedirect('/');

        $enabled = School::where('name', 'Kanilai Lower Basic')->first()->config('enabled_modules');
        $this->assertContains('students', $enabled);   // always-on core stays on
        $this->assertContains('progress', $enabled);   // ticked
        $this->assertNotContains('health', $enabled);  // left off
    }
}
