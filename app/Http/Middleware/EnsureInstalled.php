<?php

namespace App\Http\Middleware;

use App\Support\Installer\InstallerService;
use Closure;
use Illuminate\Http\Request;

/**
 * Until KUBO is set up (no School exists), every web request is funnelled to
 * the installer. Once set up, the installer routes redirect back to the app.
 */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next)
    {
        $onInstaller = $request->is('install', 'install/*');
        $installed = InstallerService::isInstalled();

        // The public demo has no installer. While a reset is wiping and reseeding
        // (half a minute), the school briefly doesn't exist — and a visitor who
        // loads a page in that window must not walk into the setup wizard.
        if (! $installed && config('app.demo')) {
            return response()->view('pages.demo.resetting', [], 503)
                ->header('Retry-After', '20');
        }

        if (! $installed && ! $onInstaller) {
            return redirect()->route('install.index');
        }

        if ($installed && $onInstaller) {
            return redirect('/');
        }

        return $next($request);
    }
}
