<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function repeated_login_attempts_for_an_account_are_throttled(): void
    {
        // The 'login' limiter allows 6 attempts/min per account+IP.
        for ($i = 0; $i < 6; $i++) {
            $this->post(route('login.attempt'), ['id' => $this->headmaster->id, 'password' => 'wrong']);
        }

        $this->post(route('login.attempt'), ['id' => $this->headmaster->id, 'password' => 'wrong'])
            ->assertStatus(429);
    }
}
