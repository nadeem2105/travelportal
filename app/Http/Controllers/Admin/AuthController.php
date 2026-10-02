<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::guard('admin')->attempt($credentials + ['status' => 'active'], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Invalid credentials or inactive account.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        $admin = Auth::guard('admin')->user();
        $admin->update(['last_login_at' => now()]);

        ActivityLogger::log('login', 'auth', 'Admin logged in');

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request)
    {
        ActivityLogger::log('logout', 'auth', 'Admin logged out');

        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
