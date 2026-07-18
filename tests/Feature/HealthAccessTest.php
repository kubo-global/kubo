<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HealthAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function caregiver_can_access_health_index()
    {
        $caregiver = User::create(['first_name' => 'Care', 'last_name' => 'Giver', 'password' => bcrypt('x')]);
        $caregiver->assignRole('caregiver');

        $this->actingAs($caregiver)->get(route('health.index'))->assertOk();
    }

    #[Test]
    public function headmaster_can_access_health_index()
    {
        $this->actingAs($this->headmaster)->get(route('health.index'))->assertOk();
    }

    #[Test]
    public function admin_is_denied_health_index_by_default()
    {
        // Post-role-split: admin = school administration, no health
        // access by default. Schools that want admin to see health
        // can re-grant via Settings → Permissions.
        $this->actingAs($this->admin)->get(route('health.index'))->assertForbidden();
    }

    #[Test]
    public function plain_teacher_is_denied_health_index()
    {
        $this->actingAs($this->teacher)->get(route('health.index'))->assertForbidden();
    }

    #[Test]
    public function assistant_coordinator_is_denied_until_granted()
    {
        Role::firstOrCreate(['name' => 'assistant_coordinator']);
        $sub = User::create(['first_name' => 'Sulayman', 'last_name' => 'Janneh', 'password' => bcrypt('x')]);
        $sub->assignRole('assistant_coordinator');

        $this->actingAs($sub)->get(route('health.index'))->assertForbidden();

        // admin (school administration) owns user management
        $this->actingAs($this->admin)
            ->post(route('users.toggle-health-access', $sub))
            ->assertRedirect();

        // Spatie caches permissions per request; the test container reuses
        // the cache, so clear it before re-acting.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($sub->fresh())->get(route('health.index'))->assertOk();
    }

    #[Test]
    public function admin_can_revoke_a_per_user_grant()
    {
        Role::firstOrCreate(['name' => 'assistant_coordinator']);
        $sub = User::create(['first_name' => 'Sulayman', 'last_name' => 'Janneh', 'password' => bcrypt('x')]);
        $sub->assignRole('assistant_coordinator');
        $sub->givePermissionTo('view medical records');

        $this->actingAs($this->admin)
            ->post(route('users.toggle-health-access', $sub))
            ->assertRedirect();

        $sub->refresh();
        $this->assertFalse($sub->hasDirectPermission('view medical records'));
    }

    #[Test]
    public function toggle_endpoint_refuses_to_alter_users_who_already_have_access_via_role()
    {
        $caregiver = User::create(['first_name' => 'Care', 'last_name' => 'Giver', 'password' => bcrypt('x')]);
        $caregiver->assignRole('caregiver');

        $this->actingAs($this->admin)
            ->post(route('users.toggle-health-access', $caregiver))
            ->assertSessionHas('error');
    }
}
