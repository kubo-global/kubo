<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * kolibri:demo-activity drives real writes into Kolibri as pupils, so its
 * guards are what keep it from ever running against a real school install:
 * refuse outside DEMO_MODE, and degrade gracefully when Kolibri is absent.
 */
class KolibriDemoActivityGuardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_refuses_to_run_outside_demo_mode(): void
    {
        config(['app.demo' => false]);

        $this->artisan('kolibri:demo-activity')
            ->expectsOutputToContain('DEMO_MODE is off')
            ->assertExitCode(1);
    }

    #[Test]
    public function without_kolibri_configured_it_leaves_the_baseline_and_succeeds(): void
    {
        config(['app.demo' => true, 'kubo-kolibri.kolibri_url' => null]);

        $this->artisan('kolibri:demo-activity')
            ->expectsOutputToContain('Kolibri is not configured')
            ->assertExitCode(0);
    }

    #[Test]
    public function without_a_provisioned_facility_it_leaves_the_baseline_and_succeeds(): void
    {
        config(['app.demo' => true, 'kubo-kolibri.kolibri_url' => 'http://localhost:9999']);
        \App\Models\School::factory()->create(['kolibri_facility_id' => null]);

        $this->artisan('kolibri:demo-activity')
            ->expectsOutputToContain('No Kolibri facility')
            ->assertExitCode(0);
    }
}
