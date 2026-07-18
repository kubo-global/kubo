<?php

namespace Tests;

use App\Models\Schoolyear;
use App\Models\Term;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, WithFaker;

    protected User $user_without_roles, $teacher, $headmaster, $admin, $systemAdmin, $student;
    protected Schoolyear $schoolyear;
    protected Term $term;

    public function setUp(): void
    {
        parent::setUp();

        $this->seed('RolesAndPermissionsSeeder');

        // Spatie's permission cache is application-level and survives
        // across tests in the same class; clear it after seeding so
        // each test sees the fresh role/permission grants.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->user_without_roles = User::create([
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail(),
            'password' => bcrypt('secret')
        ]);
        $this->student = User::create([
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail(),
            'password' => bcrypt('secret')
        ]);
        $this->student->assignRole(['student']);

        $this->teacher = User::create([
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail(),
            'password' => bcrypt('secret')
        ]);
        $this->teacher->assignRole(['teacher']);

        $this->admin = User::create([
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail(),
            'password' => bcrypt('secret')
        ]);
        $this->admin->assignRole(['admin']);

        $this->headmaster = User::create([
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail(),
            'password' => bcrypt('secret')
        ]);
        $this->headmaster->assignRole(['headmaster']);

        $this->systemAdmin = User::create([
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail(),
            'password' => bcrypt('secret')
        ]);
        $this->systemAdmin->assignRole(['system_admin']);

        $this->schoolyear = Schoolyear::create([
            'name' => '2024-2025',
            'start' => '2024-09-01',
            'end' => '2025-08-31'
        ]);

        $this->term = Term::create([
            'name' => 'Term 1',
            'start' => '2024-09-01',
            'end' => '2024-12-31',
            'schoolyear_id' => $this->schoolyear->id
        ]);
    }
}
