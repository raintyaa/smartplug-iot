<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required'],
            'password' => ['required'],
        ]);

        $loginInput = trim($request->input('email'));
        $password = $request->input('password');

        // Dukung login via email langsung ATAU via username (misal: 'rakha')
        $credentials = ['password' => $password];

        if (filter_var($loginInput, FILTER_VALIDATE_EMAIL)) {
            $credentials['email'] = $loginInput;
        } else {
            $user = User::where('name', $loginInput)
                ->orWhere('email', $loginInput . '@ranova.id')
                ->first();

            $credentials['email'] = $user ? $user->email : $loginInput;
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'Username/email atau password yang kamu masukkan salah.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->with('success', 'Berhasil keluar dari sistem.');
    }
}
