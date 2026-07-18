<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Headmaster becomes a fallback sysadmin while no system_admin is
 * assigned. Once someone is given the role, the fallback turns off.
 */
class HeadmasterSysadminFallbackTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function headmaster_gets_sysadmin_perms_when_no_system_admin_exists()
    {
        $this->systemAdmin->delete();

        // /backups is sysadmin-only; headmaster shouldn't have it from
        // their own role, but the fallback should grant it.
        $this->actingAs($this->headmaster)
            ->get(route('backup.index'))
            ->assertOk();
    }

    #[Test]
    public function headmaster_loses_fallback_once_system_admin_is_assigned()
    {
        $this->actingAs($this->headmaster)
            ->get(route('backup.index'))
            ->assertForbidden();
    }

    #[Test]
    public function archived_system_admin_does_not_count()
    {
        $this->systemAdmin->archived = true;
        $this->systemAdmin->save();

        $this->actingAs($this->headmaster)
            ->get(route('backup.index'))
            ->assertOk();
    }

    #[Test]
    public function fallback_does_not_apply_to_admin()
    {
        $this->systemAdmin->delete();

        // admin doesn't have manage backups by default and the fallback
        // only extends to headmaster, so admin still gets 403.
        $this->actingAs($this->admin)
            ->get(route('backup.index'))
            ->assertForbidden();
    }
}
