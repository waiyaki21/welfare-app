<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show()
    {
        return view('profile.show', [
            'user'                  => Auth::user(),
            'appName'               => AppSetting::appName(),
            'appSubtitle'           => AppSetting::appSubtitle(),
            'sidebarColor'          => AppSetting::sidebarColor(),
            'theme'                 => AppSetting::theme(),
            'yearlyImportEnabled'   => AppSetting::yearlyImportEnabled(),
            'monthlyImportEnabled'  => AppSetting::monthlyImportEnabled(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);
        $user->update($data);
        return redirect()->route('profile.show')->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'current_password' => 'required',
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);
        if ($user->hasPasswordAuth() && !Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }
        $user->update(['password' => Hash::make($request->password)]);
        return redirect()->route('profile.show')->with('success', 'Password updated successfully.');
    }

    public function updateAppSettings(Request $request)
    {
        $data = $request->validate([
            'app_name'     => 'required|string|max:60',
            'app_subtitle' => 'nullable|string|max:80',
        ]);
        AppSetting::set('app_name',     $data['app_name']);
        AppSetting::set('app_subtitle', $data['app_subtitle'] ?? '');
        return redirect()->route('profile.show')->with('success', 'App settings saved.');
    }

    public function updateSidebarColor(Request $request)
    {
        $data = $request->validate([
            'sidebar_color' => 'required|string|max:7|regex:/^#[0-9a-fA-F]{6}$/',
        ]);
        AppSetting::set('sidebar_color', $data['sidebar_color']);
        return redirect()->back()->with('success', 'Sidebar colour updated.');
    }

    public function updateImportSettings(Request $request)
    {
        AppSetting::set('import_yearly_enabled',  $request->boolean('import_yearly_enabled')  ? 'true' : 'false');
        AppSetting::set('import_monthly_enabled', $request->boolean('import_monthly_enabled') ? 'true' : 'false');
        return redirect()->route('profile.show')->with('success', 'Import settings saved.');
    }

    public function updateTheme(Request $request)
    {
        $data = $request->validate([
            'theme' => 'required|in:light,dark,system',
        ]);
        AppSetting::set('theme', $data['theme']);
        return redirect()->back()->with('success', 'Theme updated.');
    }
}
