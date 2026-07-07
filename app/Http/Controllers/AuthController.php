<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Invalid email or password.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        if (Auth::user()->status !== 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'This employee profile is inactive.',
            ])->onlyInput('email');
        }

        if (Schema::hasColumn('users', 'must_change_password') && Auth::user()->must_change_password) {
            return redirect()->route('password.edit')->with('warning', 'You must change your temporary password before using ISO Admin.');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showPassword()
    {
        return view('auth.password');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (!Hash::check($data['current_password'], $request->user()->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        $update = [
            'password' => Hash::make($data['password']),
        ];

        if (Schema::hasColumn('users', 'must_change_password')) {
            $update['must_change_password'] = false;
        }
        if (Schema::hasColumn('users', 'password_changed_at')) {
            $update['password_changed_at'] = now();
        }

        $request->user()->update($update);

        return redirect()->route('dashboard')->with('success', 'Password updated.');
    }
}
