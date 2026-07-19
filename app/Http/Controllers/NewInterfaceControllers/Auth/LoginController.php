<?php

namespace App\Http\Controllers\NewInterfaceControllers\Auth;


use App\Http\Controllers\Controller;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // 'guest' bounces a signed-in visitor straight back to the app, which is right
        // for a school. In the demo, seeing the sign-in screen is part of the point, so
        // showLoginForm handles it there: it signs you out and shows you the door.
        // supportLogin is the signed on-box servicing link and must work either way.
        $this->middleware('guest')->except(
            config('app.demo') ? ['logout', 'showLoginForm', 'supportLogin'] : ['logout', 'supportLogin']
        );
    }

    /*
    |--------------------------------------------------------------------------|
    |Overriden functions from AuthenticatesUsers trait                         |
    |--------------------------------------------------------------------------|
    */

    public function username()
    {
        return 'id';
    }

    public function showLoginForm(Request $request)
    {
        // The public demo hands you a role instead of a password, so /login goes to
        // the picker. Unless the visitor asked to see the real sign-in screen: that
        // screen is part of what we are showing them.
        if (config('app.demo') && ! $request->boolean('real')) {
            return redirect()->route('demo.picker');
        }

        // A sign-in screen begins a session; it must not render inside the one you
        // already have (sidebar, header and all).
        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $acceptedRoles = ['admin', 'caregiver', 'headmaster', 'teacher', 'assistant_coordinator'];
        $alwaysVisible = ['admin', 'caregiver', 'headmaster', 'assistant_coordinator'];
        $year = Schoolyear::current() ?? Schoolyear::latest();

        $users = User::role($acceptedRoles)
            ->where('archived', '!=', 1)
            // The KUBO Support account never shows on a school's login list; it is
            // only reachable via a signed link from `php artisan kubo:support`. Matched
            // by the identity that command creates — NOT by the system_admin role,
            // which real staff can hold too (e.g. a headmaster who manages backups).
            ->whereNot(fn ($q) => $q->where('first_name', 'KUBO')->where('last_name', 'Support'))
            // Non-teaching staff (admin, caregiver, headmaster, coordinator,
            // assistant_coordinator) are always shown; teachers only when
            // they have an offering in the current school year — covers both
            // class principals and subject teachers across multiple grades.
            ->where(function ($q) use ($year, $alwaysVisible) {
                $q->whereHas('roles', fn ($r) => $r->whereIn('name', $alwaysVisible))
                  ->orWhereExists(function ($sub) use ($year) {
                      $sub->select(DB::raw(1))
                          ->from('teacher_offering')
                          ->join('offerings', 'offerings.id', '=', 'teacher_offering.offering_id')
                          ->whereColumn('teacher_offering.user_id', 'users.id')
                          ->when($year, fn ($qq) => $qq->where('offerings.schoolyear_id', $year->id));
                  });
            })
            ->orderBy('first_name')
            ->get();

        return view('pages.login', ['users' => $users]);
    }

    /**
     * After logout, go straight to the login page. The AuthenticatesUsers trait
     * otherwise redirects to '/', which `Route::redirect('/', 'dashboard')` sends
     * to the auth-gated dashboard — bouncing the just-logged-out user.
     */
    protected function loggedOut(\Illuminate\Http\Request $request)
    {
        return redirect()->route('login');
    }

    /**
     * Sign in the KUBO Support account from a signed servicing link (see the
     * `support.login` route and the `kubo:support` command). The link's validity
     * is enforced by the `signed` middleware; here we only confirm the target is
     * the support account, never a normal school user.
     */
    public function supportLogin(User $user, \Illuminate\Http\Request $request)
    {
        abort_unless($user->hasRole('system_admin'), 403);

        $this->guard()->login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('success', 'Signed in as KUBO Support.');
    }
}
