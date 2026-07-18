<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The destructive round-trip (corrupt -> restore -> recovered) is proven manually
 * against a sandbox DB; these cover the command's contract without touching data.
 */
class RestoreBackupTest extends TestCase
{
    use RefreshDatabase;

    private function tempBackup(): string
    {
        $path = sys_get_temp_dir().'/kubo-restore-test-'.uniqid().'.sql.gz';
        file_put_contents($path, 'dummy');

        return $path;
    }

    /** A MySQL connection to target (the test suite itself runs on sqlite). */
    private function mysqlConnection(): string
    {
        config(['database.connections.restore_target' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'database' => 'kubo_restore_target',
            'username' => 'root',
            'password' => '',
        ]]);

        return 'restore_target';
    }

    #[Test]
    public function it_fails_when_the_backup_file_is_missing(): void
    {
        $this->artisan('kubo:restore', ['file' => '/tmp/definitely-not-here-xyz.sql.gz'])
            ->expectsOutputToContain('not found')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_rejects_a_non_mysql_connection(): void
    {
        config(['database.connections.fake_sqlite' => ['driver' => 'sqlite']]);
        $file = $this->tempBackup();

        $this->artisan('kubo:restore', ['file' => $file, '--connection' => 'fake_sqlite'])
            ->expectsOutputToContain('not a MySQL connection')
            ->assertExitCode(1);

        @unlink($file);
    }

    #[Test]
    public function dry_run_plans_a_safety_snapshot_then_restore_and_changes_nothing(): void
    {
        $file = $this->tempBackup();

        $this->artisan('kubo:restore', ['file' => $file, '--connection' => $this->mysqlConnection(), '--dry-run' => true])
            ->expectsOutputToContain('mysqldump')   // the pre-restore safety snapshot
            ->expectsOutputToContain('gunzip -c')    // the restore import (gzipped backup)
            ->expectsOutputToContain('No changes made')
            ->assertExitCode(0);

        @unlink($file);
    }

    #[Test]
    public function no_snapshot_option_skips_the_safety_dump(): void
    {
        $file = $this->tempBackup();

        $this->artisan('kubo:restore', ['file' => $file, '--connection' => $this->mysqlConnection(), '--dry-run' => true, '--no-snapshot' => true])
            ->doesntExpectOutputToContain('mysqldump')
            ->expectsOutputToContain('gunzip -c')
            ->assertExitCode(0);

        @unlink($file);
    }
}
