@extends('layouts.app')
@section('title', 'Profile & Settings')

@section('content')
<div style="max-width:680px;">

{{-- Profile header --}}
<div class="card mb-6">
    <div class="card-body">
        <div class="flex items-center gap-4">
            @if(Auth::user()->avatar)
                <img src="{{ Auth::user()->avatar }}" alt="{{ Auth::user()->name }}"
                     style="width:52px;height:52px;border-radius:50%;object-fit:cover;flex-shrink:0;">
            @else
                <div class="avatar avatar-lg">{{ Auth::user()->initials }}</div>
            @endif
            <div>
                <div style="font-family:'DM Serif Display',serif;font-size:1.2rem;color:var(--forest)">{{ Auth::user()->name }}</div>
                <div class="dim text-sm" style="margin-top:2px">{{ Auth::user()->email }}</div>
                <div style="margin-top:6px;display:flex;gap:6px;">
                    <span class="badge badge-mid">Email / Password</span>
                    <span class="badge badge-g">{{ ucfirst(Auth::user()->role) }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- App Settings --}}
<div class="card mb-6">
    <div class="card-head"><div class="card-title">App Settings</div></div>
    <div class="card-body">
        <form method="POST" action="{{ route('profile.app-settings') }}">
            @csrf
            <div class="form-group">
                <label class="form-label">App Name <span style="color:var(--rust)">*</span></label>
                <input type="text" name="app_name" class="form-control"
                       value="{{ old('app_name', $appName) }}"
                       placeholder="e.g. Athoni Welfare" required>
                <div class="form-hint">Displayed at the top of the sidebar</div>
            </div>
            <div class="form-group">
                <label class="form-label">Subtitle</label>
                <input type="text" name="app_subtitle" class="form-control"
                       value="{{ old('app_subtitle', $appSubtitle) }}"
                       placeholder="e.g. Association Ledger">
                <div class="form-hint">Smaller text beneath the app name</div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:auto;display:inline-flex;">Save App Settings</button>
        </form>
    </div>
</div>

{{-- Sidebar Colour --}}
<div class="card mb-6">
    <div class="card-head"><div class="card-title">Sidebar Colour</div></div>
    <div class="card-body">
        <form method="POST" action="{{ route('profile.sidebar-color') }}" id="sb-color-form">
            @csrf
            <div class="form-group">
                <label class="form-label">Pick a colour</label>
                <div class="flex items-center gap-4">
                    <input type="color" name="sidebar_color" id="sb-color-input"
                           value="{{ old('sidebar_color', $sidebarColor) }}"
                           style="width:52px;height:42px;padding:3px;border:1.5px solid var(--border);border-radius:var(--r-sm);cursor:pointer;background:none;"
                           oninput="previewSidebar(this.value)">
                    <div class="flex gap-2" style="flex-wrap:wrap;">
                        @foreach([
                            ['#1a3a2a','Forest (default)'],
                            ['#1e3a5f','Navy Blue'],
                            ['#2d2d2d','Charcoal'],
                            ['#3d1a3a','Plum'],
                            ['#1a2e3a','Slate'],
                            ['#3a2d1a','Walnut'],
                            ['#1a3a35','Teal Forest'],
                        ] as [$hex, $label])
                        <button type="button"
                                onclick="setColor('{{ $hex }}')"
                                title="{{ $label }}"
                                style="width:28px;height:28px;border-radius:6px;background:{{ $hex }};border:2px solid {{ $hex === $sidebarColor ? '#fff' : 'transparent' }};cursor:pointer;box-shadow:0 0 0 1px rgba(0,0,0,.15);transition:transform .1s;"
                                onmouseover="this.style.transform='scale(1.15)'"
                                onmouseout="this.style.transform='scale(1)'"
                                id="swatch-{{ ltrim($hex,'#') }}">
                        </button>
                        @endforeach
                    </div>
                </div>
                <div class="form-hint">Changes take effect after saving. The sidebar updates live as you pick.</div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:auto;display:inline-flex;">Save Colour</button>
        </form>
    </div>
</div>

{{-- Profile Info --}}
<div class="card mb-6">
    <div class="card-head"><div class="card-title">Profile Information</div></div>
    <div class="card-body">
        <form method="POST" action="{{ route('profile.update') }}">
            @csrf
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Name <span style="color:var(--rust)">*</span></label>
                    <input type="text" name="name" class="form-control"
                           value="{{ old('name', Auth::user()->name) }}" required>
                    @error('name')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Email <span style="color:var(--rust)">*</span></label>
                    <input type="email" name="email" class="form-control"
                           value="{{ old('email', Auth::user()->email) }}" required>
                    @error('email')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:auto;display:inline-flex;">Update Profile</button>
        </form>
    </div>
</div>

{{-- Import Settings --}}
<div class="card mb-6">
    <div class="card-head"><div class="card-title">Import Settings</div></div>
    <div class="card-body">
        <form method="POST" action="{{ route('profile.import-settings') }}">
            @csrf
            <div class="form-group">
                <label class="checkbox-label" style="margin-bottom:14px;">
                    <input type="checkbox" name="import_yearly_enabled" value="1"
                           {{ $yearlyImportEnabled ? 'checked' : '' }}
                           style="width:17px;height:17px;accent-color:var(--leaf);">
                    <div>
                        <div style="font-weight:500;">Full-Year Import</div>
                        <div class="text-sm text-mid" style="margin-top:2px;">
                            Show the <em>Import Year</em> button on the dashboard. Allows uploading the full annual ledger spreadsheet.
                        </div>
                    </div>
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" name="import_monthly_enabled" value="1"
                           {{ $monthlyImportEnabled ? 'checked' : '' }}
                           style="width:17px;height:17px;accent-color:var(--leaf);">
                    <div>
                        <div style="font-weight:500;">Monthly Import</div>
                        <div class="text-sm text-mid" style="margin-top:2px;">
                            Show the <em>Import Month</em> button on the dashboard. Allows downloading a member template and uploading monthly payments and welfare amounts.
                        </div>
                    </div>
                </label>
            </div>
            <button type="submit" class="btn btn-primary" style="width:auto;display:inline-flex;">Save Import Settings</button>
        </form>
    </div>
</div>

{{-- Theme --}}
<div class="card mb-6">
    <div class="card-head"><div class="card-title">Theme</div></div>
    <div class="card-body">
        <form method="POST" action="{{ route('profile.theme') }}">
            @csrf
            <div class="form-group">
                <label class="form-label">Colour mode</label>
                <div class="flex gap-3">
                    @foreach(['light' => 'Light', 'dark' => 'Dark (coming soon)', 'system' => 'System'] as $val => $lbl)
                    <label style="display:flex;align-items:center;gap:7px;cursor:{{ $val === 'dark' ? 'not-allowed' : 'pointer' }};opacity:{{ $val === 'dark' ? '.45' : '1' }};">
                        <input type="radio" name="theme" value="{{ $val }}"
                               {{ $theme === $val ? 'checked' : '' }}
                               {{ $val === 'dark' ? 'disabled' : '' }}
                               style="accent-color:var(--leaf);">
                        <span style="font-size:.9rem;">{{ $lbl }}</span>
                    </label>
                    @endforeach
                </div>
                <div class="form-hint">Dark mode is reserved for a future update.</div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:auto;display:inline-flex;">Save Theme</button>
        </form>
    </div>
</div>

{{-- Password --}}
<div class="card mb-6">
    <div class="card-head"><div class="card-title">{{ Auth::user()->hasPasswordAuth() ? 'Change Password' : 'Set a Password' }}</div></div>
    <div class="card-body">
        <form method="POST" action="{{ route('profile.password') }}">
            @csrf
            @if(Auth::user()->hasPasswordAuth())
            <div class="form-group">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-control" placeholder="••••••••">
                @error('current_password')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            @endif
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="password" class="form-control"
                           placeholder="At least 8 characters" autocomplete="new-password">
                    @error('password')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="password_confirmation" class="form-control" placeholder="Repeat">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:auto;display:inline-flex;">
                {{ Auth::user()->hasPasswordAuth() ? 'Update Password' : 'Set Password' }}
            </button>
        </form>
    </div>
</div>

</div>
@push('scripts')
<script>
function previewSidebar(hex) {
    document.documentElement.style.setProperty('--forest', hex);
    // update swatch borders
    document.querySelectorAll('[id^="swatch-"]').forEach(s => {
        s.style.borderColor = 'transparent';
    });
}
function setColor(hex) {
    document.getElementById('sb-color-input').value = hex;
    previewSidebar(hex);
    // highlight selected swatch
    document.querySelectorAll('[id^="swatch-"]').forEach(s => {
        s.style.borderColor = s.style.background === hex ? '#fff' : 'transparent';
    });
}
</script>
@endpush
@endsection
