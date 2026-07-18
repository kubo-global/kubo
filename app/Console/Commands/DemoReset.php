<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Wipe and reseed the demo database. Guarded by DEMO_MODE so it can never
 * touch a real school install. Run nightly (see the console schedule) and
 * on demand from the demo banner's "Reset demo" button.
 */
class DemoReset extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Wipe and reseed the demo database (DEMO_MODE only)';

    public function handle(): int
    {
        if (! config('app.demo')) {
            $this->error('DEMO_MODE is off — refusing to wipe the database.');

            return self::FAILURE;
        }

        $this->call('migrate:fresh', ['--force' => true, '--seed' => true]);
        $this->info('Demo database reset.');

        $this->reprovisionKolibri();

        return self::SUCCESS;
    }

    /**
     * The reset rebuilds every pupil with a new id, so the Kolibri learners the demo
     * had are now strangers: without this, a child can browse the library but cannot
     * open an exercise. Only where Kolibri is actually configured and reachable, and
     * never fatal: a demo without content is still a demo.
     */
    private function reprovisionKolibri(): void
    {
        if (! config('kubo-kolibri.kolibri_url') || ! config('kubo-kolibri.kolibri_password')) {
            return;
        }

        try {
            $this->info('Re-provisioning Kolibri learners…');
            $this->call('kolibri:provision');

            // With learners in place, replay the seeded mastery as real Kolibri
            // completions so the Learn dashboards show progress that came through
            // Kolibri, not straight into KUBO's tables. Non-fatal by its own design.
            $this->call('kolibri:demo-activity');
        } catch (\Throwable $e) {
            $this->warn('Kolibri provisioning failed: '.$e->getMessage());
            $this->warn('The demo is up; exercises will not launch until Kolibri is reachable.');
        }
    }
}
