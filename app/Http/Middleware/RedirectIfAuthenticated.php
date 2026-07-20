<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if (Auth::guard($guard)->check()) {
            // A signed-in user landing on a guest page goes to the dashboard.
            // redirect('home') pointed at the literal /home URL, which has not
            // existed since the legacy interface was retired (404); the dashboard
            // route is named 'home'.
            return redirect()->route('home');
        }

        return $next($request);
    }
}
