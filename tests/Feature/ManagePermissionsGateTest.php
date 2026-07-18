<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The role × permission matrix is gated on its own `manage permissions`
 * permission, separate from the general `manage settings`. Headmaster and
 * system_admin hold it by default; admin does not.
 */
class ManagePermissionsGateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function headmaster_can_edit_the_matrix_by_default()
    {
        $teacherRole = Role::findByName('teacher');

        $this->actingAs($this->headmaster)
            ->post(route('settings.update-role-permissions', $teacherRole), [
                'permissions' => ['view students', 'view class'],
            ])
            ->assertRedirect();
    }

    #[Test]
    public function system_admin_can_edit_the_matrix_by_default()
    {
        $teacherRole = Role::findByName('teacher');

        $this->actingAs($this->systemAdmin)
            ->post(route('settings.update-role-permissions', $teacherRole), [
                'permissions' => ['view students'],
            ])
            ->assertRedirect();
    }

    #[Test]
    public function admin_cannot_edit_the_matrix()
    {
        $teacherRole = Role::findByName('teacher');

        $this->actingAs($this->admin)
            ->post(route('settings.update-role-permissions', $teacherRole), [
                'permissions' => ['view students'],
            ])
            ->assertForbidden();
    }

    #[Test]
    public function admin_can_still_open_general_settings()
    {
        // admin keeps manage settings — only the matrix is walled off.
        $this->actingAs($this->admin)
            ->get(route('settings.index'))
            ->assertOk();
    }

    #[Test]
    public function permissions_tab_hidden_from_admin_but_shown_to_headmaster()
    {
        $this->actingAs($this->admin)
            ->get(route('settings.index'))
            ->assertDontSee('Permissions per role');

        $this->actingAs($this->headmaster)
            ->get(route('settings.index'))
            ->assertSee('Permissions per role');
    }

    #[Test]
    public function a_school_can_revoke_the_matrix_key_from_headmaster()
    {
        // Advanced-governance case: strip manage permissions from the
        // headmaster role, leaving system_admin as sole owner.
        Role::findByName('headmaster')->revokePermissionTo('manage permissions');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $teacherRole = Role::findByName('teacher');

        $this->actingAs($this->headmaster->fresh())
            ->post(route('settings.update-role-permissions', $teacherRole), [
                'permissions' => ['view students'],
            ])
            ->assertForbidden();
    }
}
