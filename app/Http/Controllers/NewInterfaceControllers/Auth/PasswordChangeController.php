<?php

namespace App\Http\Controllers\NewInterfaceControllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * The forced "set your own password" screen a user lands on when
 * must_change_password is set (after a headmaster reset, or a new account).
 */
class PasswordChangeController extends Controller
{
    public function edit()
    {
        return view('pages.auth.change-password');
    }

    public function update(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:4',
        ]);

        $user = $request->user();
        $user->password = bcrypt($request->input('password'));
        $user->must_change_password = false;
        $user->save();

        return redirect('/')->with('success', 'Your password has been set.');
    }
}
