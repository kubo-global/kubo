<?php

namespace Tests\Feature;

use App\Models\BackupLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The backup download itself — for a self-hosted school a silently broken
 * backup is the worst failure mode, so both outcomes must be LOUD: a good dump
 * downloads and logs success, a failed dump redirects with an error and logs
 * the failure (which the backup page then surfaces as overdue/warning).
 */
class BackupDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function expectedTempPath(): string
    {
        $dbName = config('database.connections.mysql.database');

        return storage_path('app/'.$dbName.'-'.now()->format('Y-m-d-His').'.sql.gz');
    }

    #[Test]
    public function a_successful_dump_downloads_and_logs_success(): void
    {
        Carbon::setTestNow('2026-07-17 10:00:00');
        Process::fake();

        // The controller checks the file the (faked) dump should have written.
        $tempPath = $this->expectedTempPath();
        file_put_contents($tempPath, str_repeat('x', 200));

        $response = $this->actingAs($this->systemAdmin)->get(route('backup.download'));

        $response->assertOk();
        $this->assertSame('application/gzip', $response->headers->get('content-type'));
        $this->assertStringContainsString('.sql.gz', $response->headers->get('content-disposition'));

        Process::assertRan(fn ($process) => str_contains($process->command, 'mysqldump'));
        $this->assertDatabaseHas('backup_logs', ['status' => 'success', 'method' => 'manual', 'destination' => 'download']);

        Carbon::setTestNow();
        @unlink($tempPath);
    }

    #[Test]
    public function a_failed_dump_redirects_with_an_error_and_logs_the_failure(): void
    {
        Carbon::setTestNow('2026-07-17 10:05:00');
        Process::fake(['*' => Process::result(errorOutput: 'mysqldump: connect failed', exitCode: 1)]);

        $response = $this->actingAs($this->systemAdmin)
            ->from(route('backup.index'))
            ->get(route('backup.download'));

        $response->assertRedirect(route('backup.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('backup_logs', ['status' => 'failed', 'method' => 'manual', 'destination' => 'download']);
        $this->assertFileDoesNotExist($this->expectedTempPath());

        Carbon::setTestNow();
    }

    #[Test]
    public function a_dump_that_writes_no_usable_file_is_a_failure_even_on_exit_zero(): void
    {
        Carbon::setTestNow('2026-07-17 10:10:00');
        Process::fake(); // exit 0, but nothing wrote the file

        $response = $this->actingAs($this->systemAdmin)
            ->from(route('backup.index'))
            ->get(route('backup.download'));

        $response->assertRedirect(route('backup.index'));
        $this->assertDatabaseHas('backup_logs', ['status' => 'failed']);
        $this->assertSame(1, BackupLog::count());

        Carbon::setTestNow();
    }
}
