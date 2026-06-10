<?php

namespace App\Http\Controllers\Web;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) return redirect()->route('home');
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();
            if ($user->status->value === 'banned') {
                Auth::logout();
                return back()->withErrors(['email' => 'Votre compte a été banni.']);
            }

            return redirect()->intended(route('home'));
        }

        return back()->withErrors(['email' => 'Identifiants incorrects.'])->onlyInput('email');
    }

    public function showRegister(): View|RedirectResponse
    {
        if (Auth::check()) return redirect()->route('home');
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'display_name'          => 'required|string|max:100',
            'email'                 => 'required|email|unique:users,email',
            'password'              => 'required|string|min:8|confirmed',
            'country'               => 'nullable|string|max:3',
        ]);

        $user = User::create([
            'display_name' => $data['display_name'],
            'email'        => $data['email'],
            'password'     => $data['password'],
            'country'      => $data['country'] ?? null,
            'role'         => UserRole::Utilisateur->value,
        ]);

        UserSetting::create(['user_id' => $user->id]);

        Auth::login($user);

        return redirect()->route('home');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);
        // In production: dispatch password reset email
        return back()->with('status', 'Si l\'adresse email existe, un lien sera envoyé.');
    }
}
