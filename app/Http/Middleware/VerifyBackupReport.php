<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the backup-status report endpoint. It's posted to by backup tooling
 * (cron), not a logged-in user, so it was previously wide open. Allow the
 * on-device cron (loopback) or a request carrying the shared BACKUP_REPORT_TOKEN;
 * reject everything else.
 */
class VerifyBackupReport
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->authorised($request), 403, 'Invalid backup report credentials.');

        return $next($request);
    }

    private function authorised(Request $request): bool
    {
        // The backup cron runs on the device itself.
        if (in_array($request->ip(), ['127.0.0.1', '::1'], true)) {
            return true;
        }

        // Off-device tooling must present the shared secret.
        $expected = (string) config('app.backup_report_token');
        $provided = (string) ($request->bearerToken() ?? $request->header('X-Backup-Token', ''));

        return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
    }
}
