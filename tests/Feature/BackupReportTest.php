<?php

namespace Tests\Feature;

use App\Models\BackupLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackupReportTest extends TestCase
{
    use RefreshDatabase;

    private array $payload = [
        'method' => 'scheduled',
        'destination' => 'usb-drive',
        'status' => 'success',
        'size_bytes' => 12345,
    ];

    #[Test]
    public function the_on_device_cron_can_report_over_loopback(): void
    {
        // Test requests come from 127.0.0.1 by default — the on-device cron.
        $this->postJson(route('backup.report'), $this->payload)->assertOk();
        $this->assertSame(1, BackupLog::count());
    }

    #[Test]
    public function a_remote_caller_without_a_token_is_rejected(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->postJson(route('backup.report'), $this->payload)
            ->assertForbidden();

        $this->assertSame(0, BackupLog::count());
    }

    #[Test]
    public function a_remote_caller_with_the_shared_token_is_accepted(): void
    {
        config(['app.backup_report_token' => 's3cret-token']);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->postJson(route('backup.report'), $this->payload, ['Authorization' => 'Bearer s3cret-token'])
            ->assertOk();

        $this->assertSame(1, BackupLog::count());
    }

    #[Test]
    public function a_remote_caller_with_a_wrong_token_is_rejected(): void
    {
        config(['app.backup_report_token' => 's3cret-token']);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->postJson(route('backup.report'), $this->payload, ['Authorization' => 'Bearer nope'])
            ->assertForbidden();
    }
}
