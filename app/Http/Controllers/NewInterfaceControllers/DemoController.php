<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The public demo signs visitors in without a password, but not as one fixed
 * person: a demo that only shows the headmaster's view hides the point, which is
 * that a teacher, a caregiver and the headmaster each see a different school.
 * Visitors pick a role and can switch at any time from the demo banner.
 */
class DemoController extends Controller
{
    /**
     * The roles worth showing, in the order a visitor should meet them, with
     * what each one is actually allowed to do.
     */
    public const ROLES = [
        'headmaster' => [
            'label' => 'Headmaster',
            'blurb' => 'The whole school: every class, term reports, the National Assessment Test, health, backups.',
        ],
        'teacher' => [
            'label' => 'Teacher',
            'blurb' => 'Their own classes: enter scores for the subjects they teach, take the register, write lesson plans.',
        ],
        'assistant_coordinator' => [
            'label' => 'Assistant coordinator',
            'blurb' => 'Academic oversight: sign off lesson plans, follow the timetable and instructional hours.',
        ],
        'caregiver' => [
            'label' => 'Caregiver',
            'blurb' => 'The health desk: who needs follow-up, growth charts, checkups, incidents and wound care.',
        ],
        'admin' => [
            'label' => 'Administrator',
            'blurb' => 'Staff accounts, roles and permissions, school settings and the school-year rollover.',
        ],
        'student' => [
            'label' => 'Pupil',
            'blurb' => 'What a child sees: their own skills and the offline library.',
        ],
    ];

    /**
     * The roles the demo school actually has somebody for, so the bar can offer a
     * one-click switch without querying per card.
     *
     * @return array<string, User>
     */
    public static function availableRoles(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $people = [];
        foreach (array_keys(self::ROLES) as $role) {
            if ($user = (new self)->demoUserFor($role)) {
                $people[$role] = $user;
            }
        }

        return $cached = $people;
    }

    public function picker(Request $request)
    {
        abort_unless(config('app.demo'), 404);

        // Picking a role means letting go of the one you have: sign out first, or the
        // picker renders inside the app's chrome (sidebar and header of the person
        // you are about to stop being).
        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return view('pages.demo.login', ['people' => self::availableRoles()]);
    }

    /** Sign in as this role's demo account. Also used to switch role mid-visit. */
    /**
     * Shareable deep link (GET /demo/{role}): "look at the demo as a teacher"
     * becomes one URL. Pupils go through their own picker (a pupil is a class +
     * name, not a single persona). Unknown roles fall back to the picker rather
     * than a 404, so a mistyped shared link still lands somewhere useful.
     */
    public function loginLink(string $role)
    {
        abort_unless(config('app.demo'), 404);

        $role = ['coordinator' => 'assistant_coordinator'][$role] ?? $role;
        if ($role === 'pupil' || $role === 'student') {
            return redirect()->route('student-login.select-grade');
        }
        if (! array_key_exists($role, self::ROLES)) {
            return redirect()->route('demo.picker');
        }

        return $this->login($role);
    }

    public function login(string $role)
    {
        abort_unless(config('app.demo'), 404);
        abort_unless(array_key_exists($role, self::ROLES), 404);

        $user = $this->demoUserFor($role);
        abort_if(! $user, 404, 'No demo account for this role.');

        Auth::login($user);

        // No flash: the demo bar already says who you are, and an app-level
        // "you are now signed in" reads like a notification the school would get.
        return redirect()->route('home');
    }

    /**
     * The demo's stand-in for a role: the first account that has it. `archived`
     * may be NULL, which SQL's `archived != 1` would drop, so match that too.
     */
    private function demoUserFor(string $role): ?User
    {
        return User::role($role)
            ->where(fn ($q) => $q->whereNull('archived')->orWhere('archived', false))
            ->orderBy('id')
            ->first();
    }
}
