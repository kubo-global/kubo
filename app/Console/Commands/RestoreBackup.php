<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Restore the database from a KUBO backup (.sql.gz from the Backups page, or a
 * plain .sql). DESTRUCTIVE: it replaces the current data. Run on the device by a
 * technician. Takes a safety snapshot first so a restore is itself reversible.
 *
 *   php artisan kubo:restore /media/usb/kubo-2026-06-25.sql.gz
 */
class RestoreBackup extends Command
{
    protected $signature = 'kubo:restore
        {file : Path to a .sql.gz (or .sql) backup file}
        {--connection= : DB connection to restore into (defaults to the app default)}
        {--force : Skip the confirmation prompt}
        {--no-snapshot : Skip the pre-restore safety snapshot}
        {--dry-run : Show what would happen without changing anything}';

    protected $description = 'Restore the database from a KUBO backup. Destructive: replaces current data.';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        if (! is_file($file) || ! is_readable($file)) {
            $this->error("Backup file not found or unreadable: {$file}");

            return self::FAILURE;
        }

        $connection = $this->option('connection') ?: config('database.default');
        $cfg = config("database.connections.{$connection}");
        if (! $cfg || ($cfg['driver'] ?? null) !== 'mysql') {
            $this->error("Connection '{$connection}' is not a MySQL connection.");

            return self::FAILURE;
        }

        $db = $cfg['database'];
        $gzipped = str_ends_with(strtolower($file), '.gz');
        $dryRun = (bool) $this->option('dry-run');

        $this->warn("This REPLACES all data in the '{$db}' database with:");
        $this->line("  {$file}");

        $snapshotPath = null;
        if (! $this->option('no-snapshot')) {
            $snapshotPath = storage_path('app/pre-restore-'.$db.'-'.now()->format('Y-m-d-His').'.sql.gz');
            $this->line('A safety snapshot of the current data will be saved to:');
            $this->line("  {$snapshotPath}");
        }

        if (! $dryRun && ! $this->option('force') && ! $this->confirm('Continue with the restore?')) {
            $this->info('Aborted, nothing was changed.');

            return self::SUCCESS;
        }

        // 1) Safety snapshot — so the restore itself can be undone.
        if ($snapshotPath) {
            $dumpCmd = sprintf('mysqldump %s | gzip > %s 2>&1', $this->connArgs($cfg), escapeshellarg($snapshotPath));
            if ($dryRun) {
                $this->line('[dry-run] snapshot: '.$dumpCmd);
            } else {
                exec($dumpCmd, $out, $code);
                if ($code !== 0 || ! is_file($snapshotPath) || filesize($snapshotPath) < 100) {
                    $this->error('Safety snapshot failed; aborting before any changes were made.');

                    return self::FAILURE;
                }
                $this->info('Safety snapshot saved ('.$this->humanSize((int) filesize($snapshotPath)).').');
            }
        }

        // 2) Import the backup.
        $mysql = 'mysql '.$this->connArgs($cfg);
        $restoreCmd = $gzipped
            ? sprintf('gunzip -c %s | %s 2>&1', escapeshellarg($file), $mysql)
            : sprintf('%s < %s 2>&1', $mysql, escapeshellarg($file));

        if ($dryRun) {
            $this->line('[dry-run] restore: '.$restoreCmd);
            $this->info('[dry-run] No changes made.');

            return self::SUCCESS;
        }

        exec($restoreCmd, $out2, $code2);
        if ($code2 !== 0) {
            $this->error('Restore failed (exit '.$code2.'): '.implode("\n", array_slice($out2, 0, 5)));
            if ($snapshotPath) {
                $this->line("Recover the pre-restore state from: {$snapshotPath}");
            }

            return self::FAILURE;
        }

        $this->info("Restore complete — '{$db}' now reflects {$file}.");

        return self::SUCCESS;
    }

    /** Shared mysql/mysqldump connection arguments, ending with the database name. */
    private function connArgs(array $cfg): string
    {
        $args = '-u '.escapeshellarg($cfg['username'] ?? 'root');
        if (! empty($cfg['password'])) {
            $args .= ' -p'.escapeshellarg($cfg['password']);
        }
        if (! empty($cfg['host'])) {
            $args .= ' -h '.escapeshellarg($cfg['host']);
        }
        if (! empty($cfg['unix_socket'])) {
            $args .= ' --socket='.escapeshellarg($cfg['unix_socket']);
        }

        return $args.' '.escapeshellarg($cfg['database']);
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        return round($bytes / 1024, 1).' KB';
    }
}
