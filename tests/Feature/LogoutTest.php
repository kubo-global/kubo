<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function logout_redirects_to_the_login_page_not_the_auth_gated_dashboard(): void
    {
        // '/' redirects to /dashboard (auth-gated); logging out must not bounce
        // the just-logged-out user through it, so it goes straight to /login.
        $this->actingAs($this->headmaster)
            ->get(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
