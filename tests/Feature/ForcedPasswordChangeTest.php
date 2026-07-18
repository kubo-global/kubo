<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ForcedPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_flagged_user_is_sent_to_set_their_password(): void
    {
        $this->teacher->update(['must_change_password' => true]);

        $this->actingAs($this->teacher)
            ->get('/dashboard')
            ->assertRedirect(route('password.change'));
    }

    #[Test]
    public function the_change_screen_is_reachable_while_flagged(): void
    {
        $this->teacher->update(['must_change_password' => true]);

        $this->actingAs($this->teacher)
            ->get(route('password.change'))
            ->assertOk()
            ->assertSee('Set your password');
    }

    #[Test]
    public function a_user_without_the_flag_is_not_redirected(): void
    {
        $this->actingAs($this->teacher)
            ->get('/dashboard')
            ->assertOk();
    }

    #[Test]
    public function setting_a_password_clears_the_flag(): void
    {
        $this->teacher->update(['must_change_password' => true, 'password' => bcrypt('secret')]);

        $this->actingAs($this->teacher)
            ->post(route('password.update'), ['password' => 'chosen-one'])
            ->assertRedirect('/');

        $fresh = $this->teacher->fresh();
        $this->assertFalse($fresh->must_change_password);
        $this->assertTrue(Hash::check('chosen-one', $fresh->password));
    }
}
