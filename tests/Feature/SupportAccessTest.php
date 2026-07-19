<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `kubo:support` prints a signed, expiring sign-in link for a hidden support
 * account. Access is gated by running the command on the machine (the link is
 * signed with this install's APP_KEY); nothing standing sits on the login screen.
 */
class SupportAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_command_creates_a_hidden_support_account(): void
    {
        $this->artisan('kubo:support')->assertSuccessful();

        $user = User::where('first_name', 'KUBO')->where('last_name', 'Support')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('system_admin'));
        $this->assertTrue($user->hasRole('admin'));
    }

    #[Test]
    public function a_valid_signed_link_signs_in_the_support_account(): void
    {
        $this->artisan('kubo:support');
        $user = User::where('first_name', 'KUBO')->where('last_name', 'Support')->first();

        $url = URL::temporarySignedRoute('support.login', now()->addMinutes(15), ['user' => $user->id]);

        $this->get($url)->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function an_unsigned_or_tampered_link_is_rejected(): void
    {
        $this->artisan('kubo:support');
        $user = User::where('first_name', 'KUBO')->first();

        // No signature at all.
        $this->get(route('support.login', ['user' => $user->id]))->assertForbidden();
        $this->assertGuest();
    }

    #[Test]
    public function a_signed_link_only_works_for_the_support_account(): void
    {
        $normal = User::factory()->create(); // no system_admin role
        $url = URL::temporarySignedRoute('support.login', now()->addMinutes(15), ['user' => $normal->id]);

        $this->get($url)->assertForbidden();
        $this->assertGuest();
    }

    #[Test]
    public function the_support_account_is_hidden_from_the_login_list(): void
    {
        $this->artisan('kubo:support');

        $this->get(route('login'))->assertOk()->assertDontSee('KUBO Support');
    }

    #[Test]
    public function real_staff_holding_system_admin_still_show_on_the_login_list(): void
    {
        // A real headmaster can hold system_admin (backups etc.); only the KUBO
        // Support identity is hidden, not everyone with the role. Regression: a
        // Swallow headmaster vanished from the picker and could not sign in.
        $head = \App\Models\User::create([
            'first_name' => 'Suwaibatou',
            'last_name' => 'Bah',
            'password' => bcrypt('secret'),
            'archived' => false,
        ]);
        $head->syncRoles(['headmaster', 'system_admin']);

        $this->get(route('login'))->assertOk()->assertSee('Suwaibatou');
    }
}
