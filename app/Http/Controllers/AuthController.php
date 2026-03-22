<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    // ── Show forms ───────────────────────────────────────────────────────────

    public function showLogin()
    {
        if (Auth::check()) return redirect()->route('dashboard');
        return view('auth.login');
    }

    public function showRegister()
    {
        if (Auth::check()) return redirect()->route('dashboard');
        // Only allow registration if no users exist yet (first-run) or if already logged in as admin
        if (User::count() > 0 && !Auth::check()) {
            return redirect()->route('auth.login')
                ->with('info', 'Registration is closed. Contact your administrator.');
        }
        return view('auth.register');
    }

    // ── Conventional auth ────────────────────────────────────────────────────

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors(['email' => 'These credentials do not match our records.'])
            ->onlyInput('email');
    }

    public function register(Request $request)
    {
        // Only allow if no users exist yet
        if (User::count() > 0) {
            return redirect()->route('auth.login')
                ->with('info', 'Registration is closed.');
        }

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'],
            'role'     => 'admin',
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('success', "Welcome, {$user->name}! Your account has been created.");
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('auth.login');
    }

    // ── Google OAuth ─────────────────────────────────────────────────────────

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect()->route('auth.login')
                ->withErrors(['email' => 'Google sign-in failed. Please try again.']);
        }

        // Find or create user
        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($user) {
            // Link Google ID if not already linked
            if (!$user->google_id) {
                $user->update(['google_id' => $googleUser->getId(), 'avatar' => $googleUser->getAvatar()]);
            }
        } else {
            // Only allow new Google sign-ups if no users exist yet
            if (User::count() > 0) {
                return redirect()->route('auth.login')
                    ->withErrors(['email' => 'No account found for this Google address. Contact your administrator.']);
            }

            $user = User::create([
                'name'               => $googleUser->getName(),
                'email'              => $googleUser->getEmail(),
                'google_id'          => $googleUser->getId(),
                'avatar'             => $googleUser->getAvatar(),
                'role'               => 'admin',
                'email_verified_at'  => now(),
            ]);
        }

        Auth::login($user, true);
        return redirect()->route('dashboard');
    }
}
