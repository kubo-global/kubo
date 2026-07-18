<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use App\Http\Controllers\Controller;
use App\Models\BackupLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

class BackupController extends Controller
{
    /**
     * Backup status page — shows history and warnings.
     */
    public function index()
    {
        $lastBackup = BackupLog::lastSuccessful();
        $isOverdue = BackupLog::isOverdue(7);
        $recentLogs = BackupLog::latest()->take(20)->get();

        return view('pages.backup.index', [
            'lastBackup' => $lastBackup,
            'isOverdue' => $isOverdue,
            'recentLogs' => $recentLogs,
        ]);
    }

    /**
     * Download a fresh database backup as .sql.gz
     */
    public function download()
    {
        $dbName = config('database.connections.mysql.database');
        $dbUser = config('database.connections.mysql.username');
        $dbPass = config('database.connections.mysql.password');
        $dbHost = config('database.connections.mysql.host');
        $dbSocket = config('database.connections.mysql.unix_socket');

        $filename = $dbName . '-' . now()->format('Y-m-d-His') . '.sql.gz';
        $tempPath = storage_path("app/{$filename}");

        // Build mysqldump command
        $passArg = $dbPass ? "-p" . escapeshellarg($dbPass) : '';
        $socketArg = $dbSocket ? "--socket=" . escapeshellarg($dbSocket) : '';
        $cmd = sprintf(
            'mysqldump -u %s %s -h %s %s %s | gzip > %s 2>&1',
            escapeshellarg($dbUser),
            $passArg,
            escapeshellarg($dbHost),
            $socketArg,
            escapeshellarg($dbName),
            escapeshellarg($tempPath)
        );

        // Through the Process facade (not exec) so tests can fake the dump.
        $result = Process::run($cmd);

        if (! $result->successful() || !file_exists($tempPath) || filesize($tempPath) < 100) {
            BackupLog::recordFailure('manual', 'download', trim($result->errorOutput().' '.$result->output()) ?: 'mysqldump produced no usable file', auth()->id());

            // Clean up
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            return back()->with('error', 'Backup failed. Check that MySQL is running.');
        }

        $size = filesize($tempPath);
        BackupLog::recordSuccess('manual', 'download', $size, $filename, auth()->id());

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/gzip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * API endpoint for external backup tools to report status.
     * POST /api/backup/report with JSON body:
     * { "method": "scheduled", "destination": "usb", "status": "success", "size_bytes": 12345 }
     *
     * DevOps can call this from their backup scripts to keep KUBO informed.
     */
    public function report(Request $request)
    {
        $validated = $request->validate([
            'method' => 'required|string|in:scheduled,external,manual',
            'destination' => 'required|string',
            'status' => 'required|string|in:success,failed',
            'size_bytes' => 'nullable|integer',
            'file_path' => 'nullable|string',
            'error' => 'nullable|string',
        ]);

        if ($validated['status'] === 'success') {
            BackupLog::recordSuccess(
                $validated['method'],
                $validated['destination'],
                $validated['size_bytes'] ?? null,
                $validated['file_path'] ?? null,
            );
        } else {
            BackupLog::recordFailure(
                $validated['method'],
                $validated['destination'],
                $validated['error'] ?? 'Unknown error',
            );
        }

        return response()->json(['ok' => true]);
    }
}
