<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * After a password reset (or on a new staff account), the user must choose their
 * own password before doing anything else. Redirect them to the change screen
 * until they do — except on that screen itself, and logout.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->must_change_password
            && ! $request->routeIs('password.change', 'password.update', 'logout')) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
