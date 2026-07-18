<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A logged-out visitor hitting an auth-gated page should land on the login
 * screen, not a bare 401 (KUBO uses the framework Authenticate middleware,
 * whose default redirect is null).
 */
class GuestRedirectTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_guest_on_an_auth_gated_page_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    #[Test]
    public function a_guest_at_the_root_ends_up_on_login(): void
    {
        // "/" redirects to /dashboard (auth-gated), which now bounces guests to login.
        $this->followingRedirects()->get('/')->assertOk()->assertSee('Welcome to KUBO');
    }

    #[Test]
    public function an_api_style_request_still_gets_a_401(): void
    {
        $this->getJson('/dashboard')->assertUnauthorized();
    }
}
