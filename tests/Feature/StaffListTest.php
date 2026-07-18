<?php

namespace Tests\Feature;

use App\Models\StaffProfile;
use App\Models\StaffStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Staff list: a printable roster (No · Name · PRN · TIN · Gender · Status ·
 * Date of Appointment · Date of Confirmation · Contact). Employment facts live
 * on StaffProfile (staff-only); gender + phone on the shared Profile.
 */
class StaffListTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_headmaster_can_download_the_staff_list_pdf(): void
    {
        $this->actingAs($this->headmaster)
            ->get(route('users.staff-list'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    #[Test]
    public function a_teacher_cannot_reach_the_staff_list(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('users.staff-list'))
            ->assertForbidden();
    }

    #[Test]
    public function editing_a_staff_member_saves_the_employment_record_and_demographics(): void
    {
        $user = User::create(['first_name' => 'Test', 'last_name' => 'Teacher', 'password' => bcrypt('x')]);
        $user->assignRole('teacher');
        $status = StaffStatus::create(['label' => 'QT', 'display_order' => 1]);

        $this->actingAs($this->headmaster)
            ->put(route('users.update', $user), [
                'first_name' => 'Test',
                'last_name' => 'Teacher',
                'roles' => ['teacher'],
                'prn' => '1901999',
                'tin' => '0610999',
                'staff_status_id' => $status->id,
                'appointed_on' => '2015-09-01',
                'confirmed_on' => '2017-01-26',
                'gender' => 'F',
                'primary_phone' => '7770000',
            ])
            ->assertRedirect(route('users.index'));

        $user->refresh()->load('staffProfile', 'profile');
        $this->assertSame('1901999', $user->staffProfile->prn);
        $this->assertSame('0610999', $user->staffProfile->tin);
        $this->assertSame($status->id, $user->staffProfile->staff_status_id);
        $this->assertSame('2015-09-01', $user->staffProfile->appointed_on->format('Y-m-d'));
        $this->assertSame('F', $user->profile->gender);
        $this->assertSame('7770000', $user->profile->primary_phone);
    }

    #[Test]
    public function the_employment_record_is_kept_off_the_shared_profile(): void
    {
        // Sanity: staff HR fields belong to StaffProfile, not Profile.
        $this->assertContains('prn', (new StaffProfile)->getFillable());
        $this->assertNotContains('prn', array_keys((new \App\Models\Profile)->getAttributes()));
    }

    #[Test]
    public function a_staff_status_can_be_added_and_removed_from_settings(): void
    {
        $this->actingAs($this->headmaster)
            ->post(route('settings.store-staff-status'), ['label' => 'DHMC', 'description' => 'Direct HMC'])
            ->assertRedirect();

        $status = StaffStatus::where('label', 'DHMC')->first();
        $this->assertNotNull($status);

        $this->actingAs($this->headmaster)
            ->delete(route('settings.destroy-staff-status', $status))
            ->assertRedirect();

        $this->assertNull(StaffStatus::find($status->id));
    }
}
