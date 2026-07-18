<?php

namespace App\Providers;

use App\Models\Enrollment;
use App\Observers\EnrollmentObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);

        // Never leak stack traces on a deployed school server, even if a copied
        // .env left APP_DEBUG=true. Production is the floor; debug stays off.
        if ($this->app->isProduction()) {
            config(['app.debug' => false]);
        }

        // Throttle staff login attempts (per account + IP) so the password
        // picker can't be brute-forced. Generous enough for honest fumbling.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(6)->by(((string) $request->input('id')).'|'.$request->ip());
        });

        Enrollment::observe(EnrollmentObserver::class);

        //Set locale of faker to Nigerian for generation of african names.
        $this->app->singleton(\Faker\Generator::class, function () {
            return \Faker\Factory::create('en_NG');
        });

        // Headmaster-as-fallback-sysadmin: when no user has the
        // system_admin role yet, treat headmasters as having the
        // system_admin-only permissions so the school isn't locked
        // out of backup/rollover work. Manage users isn't in the
        // fallback set because headmasters have it directly via their
        // role grant.
        $sysadminPerms = ['manage backups', 'manage rollover'];
        Gate::before(function ($user, string $ability) use ($sysadminPerms) {
            if (!in_array($ability, $sysadminPerms, true)) {
                return null; // not our concern
            }
            if (!$user->hasRole('headmaster')) {
                return null;
            }
            if ($this->aSystemAdminExists()) {
                return null;
            }
            return true;
        });
    }

    /**
     * Memoised on the app container (per-request): does any non-archived
     * user hold system_admin? Used by the Gate::before fallback so the
     * check is one query per request, not one per permission lookup.
     * Container memo (vs static) so it resets cleanly between tests.
     */
    private function aSystemAdminExists(): bool
    {
        $key = 'fallback.system_admin_exists';

        if ($this->app->bound($key)) {
            return $this->app->make($key);
        }

        $exists = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->join('users', function ($j) {
                $j->on('users.id', '=', 'model_has_roles.model_id')
                  ->where('model_has_roles.model_type', 'App\\Models\\User');
            })
            ->where('roles.name', 'system_admin')
            ->where('users.archived', false)
            ->exists();

        $this->app->instance($key, $exists);
        return $exists;
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // registration commented because laravel upgrades -- robby & shane

        if ($this->app->environment() !== 'production') {
            //$this->app->register(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class);
        }
    }
}
