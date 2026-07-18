<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_404_page_is_branded(): void
    {
        $this->get('/a-route-that-does-not-exist-xyz')
            ->assertNotFound()
            ->assertSee('Page not found')
            ->assertSee('KUBO');
    }

    #[Test]
    public function the_403_page_is_branded(): void
    {
        // A plain teacher lacks 'manage settings'.
        $this->actingAs($this->teacher)
            ->get(route('settings.index'))
            ->assertForbidden()
            ->assertSee('No access');
    }
}
