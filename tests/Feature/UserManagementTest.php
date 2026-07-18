<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ports the legacy admin UsersTest coverage onto the new User Management
 * interface. All routes are gated by auth + permission:manage users; the
 * headmaster role holds it.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_user_can_be_created_with_a_role(): void
    {
        $this->actingAs($this->headmaster)
            ->post(route('users.store'), [
                'first_name' => 'Amie',
                'last_name' => 'Jallow',
                'email' => 'amie@example.com',
                'password' => 'secret',
                'roles' => ['teacher'],
            ])
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'first_name' => 'Amie',
            'last_name' => 'Jallow',
            'email' => 'amie@example.com',
        ]);

        $user = User::where('email', 'amie@example.com')->first();
        $this->assertTrue($user->hasRole('teacher'));
    }

    #[Test]
    public function a_user_can_be_updated(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Old',
            'last_name' => 'Name',
            'email' => 'old@example.com',
        ]);
        $user->assignRole('teacher');

        $this->actingAs($this->headmaster)
            ->put(route('users.update', $user), [
                'first_name' => 'New',
                'last_name' => 'Name',
                'email' => 'new@example.com',
                'roles' => ['headmaster'],
            ])
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'New',
            'email' => 'new@example.com',
        ]);
        $this->assertTrue($user->fresh()->hasRole('headmaster'));
    }

    #[Test]
    public function a_users_password_can_be_reset(): void
    {
        $user = User::factory()->create(['password' => bcrypt('original')]);

        $this->actingAs($this->headmaster)
            ->post(route('users.reset-password', $user))
            ->assertRedirect();

        // Controller resets to the literal 'secret' and forces a change on next sign-in.
        $this->assertTrue(Hash::check('secret', $user->fresh()->password));
        $this->assertTrue($user->fresh()->must_change_password);
    }

    #[Test]
    public function a_user_can_be_archived_and_restored(): void
    {
        $user = User::factory()->create(['archived' => false]);

        $this->actingAs($this->headmaster)
            ->post(route('users.toggle-archive', $user))
            ->assertRedirect();
        $this->assertTrue((bool) $user->fresh()->archived);

        $this->actingAs($this->headmaster)
            ->post(route('users.toggle-archive', $user))
            ->assertRedirect();
        $this->assertFalse((bool) $user->fresh()->archived);
    }

    #[Test]
    public function a_user_without_enrollments_can_be_deleted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->headmaster)
            ->delete(route('users.destroy', $user))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    #[Test]
    public function a_plain_teacher_cannot_manage_users(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('users.store'), [
                'first_name' => 'Blocked',
                'last_name' => 'Teacher',
                'password' => 'secret',
                'roles' => ['teacher'],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['first_name' => 'Blocked', 'last_name' => 'Teacher']);
    }
}
