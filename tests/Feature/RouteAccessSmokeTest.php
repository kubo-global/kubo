<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A blunt safety net: hit every main page as every role and assert the access is
 * what we expect. Catches both regressions (a page starts 500'ing) and silent
 * access changes (a page becomes reachable by the wrong role). The matrix below
 * IS the documented access model, keep it in sync with the route middleware.
 */
class RouteAccessSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $caregiver;

    public function setUp(): void
    {
        parent::setUp();

        // Most pages assume a configured school exists.
        School::factory()->create();

        $this->caregiver = User::create([
            'first_name' => 'Care', 'last_name' => 'Giver',
            'email' => 'caregiver@example.test', 'password' => bcrypt('secret'),
        ]);
        $this->caregiver->assignRole('caregiver');
    }

    /** route name => the roles that should get through (everyone else must be 403). */
    private function matrix(): array
    {
        return [
            'home' => ['headmaster', 'admin', 'teacher', 'student', 'systemAdmin', 'caregiver'],
            'students.index' => ['headmaster', 'admin', 'teacher', 'caregiver'], // caregiver browses pupils to record health
            'reporting.grades' => ['headmaster', 'admin', 'teacher'],
            'termreports.overview' => ['headmaster', 'admin', 'teacher'],
            'lesson-plans.index' => ['headmaster', 'admin', 'teacher'],
            'progress.index' => ['headmaster', 'admin', 'teacher'],
            'content.index' => ['headmaster', 'admin'], // exercise & video mapper is an admin/setup task
            'exercises.index' => ['headmaster', 'admin', 'teacher'],
            'health.index' => ['headmaster', 'caregiver'],
            'health.wound-cases.index' => ['headmaster', 'caregiver'],
            'health.incidents.index' => ['headmaster', 'caregiver'],
            'users.index' => ['headmaster', 'admin'],
            'settings.index' => ['headmaster', 'admin', 'systemAdmin'],
            'backup.index' => ['systemAdmin'],
            'rollover.index' => ['systemAdmin'],
        ];
    }

    #[Test]
    public function every_main_page_enforces_the_expected_access(): void
    {
        $byRole = [
            'headmaster' => $this->headmaster,
            'admin' => $this->admin,
            'teacher' => $this->teacher,
            'student' => $this->student,
            'systemAdmin' => $this->systemAdmin,
            'caregiver' => $this->caregiver,
        ];

        foreach ($this->matrix() as $routeName => $allowed) {
            foreach ($byRole as $role => $user) {
                $status = $this->actingAs($user)->get(route($routeName))->status();

                if (in_array($role, $allowed, true)) {
                    $this->assertLessThan(400, $status, "{$role} should reach '{$routeName}' but got {$status}");
                } else {
                    $this->assertSame(403, $status, "{$role} should be denied '{$routeName}' but got {$status}");
                }
            }
        }
    }
}
