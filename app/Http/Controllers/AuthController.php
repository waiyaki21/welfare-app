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
        if (User::count() == 0 && ! Auth::check()) {
            return redirect()->route('auth.register')
                ->with('info', 'No Users Yet, Start with registration.');
        }
        return view('auth.login');
    }

    public function showRegister()
    {
        if (Auth::check()) return redirect()->route('dashboard');
        if (User::count() > 0 && ! Auth::check()) {
            return redirect()->route('auth.login')
                ->with('info', 'Registration is closed. Contact your administrator.');
        }
        return view('auth.register');
    }

    public function showForgotPassword()
    {
        if (Auth::check()) return redirect()->route('dashboard');
        return view('auth.forgot-password');
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

    // ── Forgot / Reset Password (local app — no email link needed) ───────────
    //
    // Because this is a desktop app with a single admin account, we skip the
    // standard token-email flow. The user provides their registered email and
    // a new password; if the email matches the account on record the password
    // is updated immediately.

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::where('email', $data['email'])->first();

        // Always show the same success message — prevents email enumeration
        if (! $user) {
            return back()->with(
                'success',
                'If that email is registered, your password has been updated. Please sign in.'
            );
        }

        $user->update(['password' => Hash::make($data['password'])]);

        // Log out any existing session for this user across devices
        Auth::logoutOtherDevices($data['password']);

        return redirect()->route('auth.login')
            ->with('success', 'Password updated successfully. Please sign in with your new password.');
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

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($user) {
            if (! $user->google_id) {
                $user->update(['google_id' => $googleUser->getId(), 'avatar' => $googleUser->getAvatar()]);
            }
        } else {
            if (User::count() > 0) {
                return redirect()->route('auth.login')
                    ->withErrors(['email' => 'No account found for this Google address. Contact your administrator.']);
            }

            $user = User::create([
                'name'              => $googleUser->getName(),
                'email'             => $googleUser->getEmail(),
                'google_id'         => $googleUser->getId(),
                'avatar'            => $googleUser->getAvatar(),
                'role'              => 'admin',
                'email_verified_at' => now(),
            ]);
        }

        Auth::login($user, true);
        return redirect()->route('dashboard');
    }
}
