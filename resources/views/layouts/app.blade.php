<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Athoni Welfare — @yield('title', 'Dashboard')</title>
    <link rel="icon" type="image/png" href="/icon.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @php
        $appName     = \App\Models\AppSetting::appName();
        $appSub      = \App\Models\AppSetting::appSubtitle();
        $sbColor     = \App\Models\AppSetting::get('sidebar_color', '#1a3a2a');
        $sbCollapsed = request()->cookie('sb_collapsed') === '1';

        $fyCount     = \App\Models\FinancialYear::count();
        $years       = \App\Models\FinancialYear::orderByDesc('year')->pluck('year');

        $selectedYear = (int) request('year', $years->first() ?? date('Y'));

        $yearRecord = \App\Models\FinancialYear::where('year', $selectedYear)
            ->withCount(['memberFinancials', 'expenditures', 'expenses'])
            ->first();

        $memCount  = $yearRecord ? $yearRecord->member_financials_count : 0;
        $expCount  = $yearRecord ? $yearRecord->expenditures_count : 0;
        $expensesCount = $yearRecord ? $yearRecord->expenses_count : 0;

        // Latest monthly payments
        $latestMonthSummary = $yearRecord ? $yearRecord->latestMonthlySummary() : [
            'month_number' => null,
            'month_name' => null,
            'count' => 0,
            'total' => 0
        ];
    @endphp
    <style>
        :root {
            --forest:{{ $sbColor }};
            --forest-hover: color-mix(in srgb, {{ $sbColor }} 80%, white);
            --leaf:#2d6a4f; --sage:#52b788; --mist:#d8f3dc; --mint:#b7e4c7;
            --cream:#f7f5f0; --amber:#e9b44c; --rust:#c0392b; --gold:#f0a500;
            --ink:#1c1c1e; --mid:#6b7280; --soft:#9ca3af; --border:#e5e7eb;
            --white:#ffffff; --surface:#f9fafb;
            --shadow-sm:0 1px 2px rgba(0,0,0,.06);
            --shadow:0 1px 3px rgba(0,0,0,.08),0 4px 12px rgba(0,0,0,.05);
            --r:12px; --r-sm:8px;
            --sb-w:220px;
        }
        *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
    </style>
    @stack('styles')
</head>
<body class="{{ request()->cookie('sb_collapsed') === '1' ? 'sb-collapsed' : '' }}">

{{-- ══ Sidebar ════════════════════════════════════════════════════ --}}
<aside class="sidebar" id="sidebar">
    <div class="sb-brand">
        <div class="sb-brand-text">
            <h1>{{ $appName }}</h1>
            <small class="version-tag font-sans">v{{ config('app.version') }}</small>
        </div>
        <button class="sb-toggle" id="sb-toggle-btn" title="Toggle sidebar">
            <div class="hamburger-icon">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </button>
    </div>

    <nav class="sb-nav">
        <div class="sb-section">Overview</div>
        <a href="{{ route('dashboard') }}"
           class="sb-item {{ request()->routeIs('dashboard') ? 'active' : '' }}"
           data-label="Dashboard">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            <span class="sb-item-label">Dashboard</span>
        </a>

        <div class="sb-section">Records</div>
        <a href="{{ route('members.index') }}"
           class="sb-item {{ request()->routeIs('members.*') ? 'active' : '' }}"
           data-label="Members">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span class="sb-item-label">Members</span>
            @if($memCount > 100)
                <span class="sb-dot" title="{{ $memCount }} members"></span>
            @elseif($memCount > 0)
                <span class="sb-badge">{{ $memCount }}</span>
            @endif
        </a>

        <a href="{{ route('payments.index') }}"
           class="sb-item {{ request()->routeIs('payments.*') ? 'active' : '' }}"
           data-label="Payments">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            <span class="sb-item-label">Payments</span>
            @if($latestMonthSummary['count'] > 100)
                <span class="sb-dot" title="{{ $latestMonthSummary['count'] }} {{ $latestMonthSummary['month_name'] ?? 'N/A' }} Payments"></span>
            @elseif($latestMonthSummary['count'] > 0)
                <span class="sb-badge">{{ $latestMonthSummary['count'] }}</span>
            @endif
        </a>

        <a href="{{ route('expenses.index') }}"
           class="sb-item {{ request()->routeIs('expenses.*') ? 'active' : '' }}"
           data-label="Expenses">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            <span class="sb-item-label">Expenses</span>
            @if($expensesCount > 100)
                <span class="sb-dot" title="{{ $expensesCount }} expenses"></span>
            @elseif($expensesCount > 0)
                <span class="sb-badge">{{ $expensesCount }}</span>
            @endif
        </a>

        <a href="{{ route('expenditures.index', ['year' => $selectedYear]) }}"
           class="sb-item {{ request()->routeIs('expenditures.*') ? 'active' : '' }}"
           data-label="Expenditures">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h10"/></svg>
            <span class="sb-item-label">Expenditures</span>
            @if($expCount > 100)
                <span class="sb-dot" title="{{ $expCount }} expenditures"></span>
            @elseif($expCount > 0)
                <span class="sb-badge">{{ $expCount }}</span>
            @endif
        </a>

        <div class="sb-section">Tools</div>
        <a href="{{ route('expense-categories.index') }}"
           class="sb-item {{ request()->routeIs('expense-categories.*') ? 'active' : '' }}"
           data-label="Expense Categories">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            <span class="sb-item-label">Expense Categories</span>
        </a>

        <a href="{{ route('financial-years.index') }}"
           class="sb-item {{ request()->routeIs('financial-years.*') ? 'active' : '' }}"
           data-label="Financial Years">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span class="sb-item-label">Financial Years</span>
            @if($fyCount > 100)
                <span class="sb-dot" title="{{ $fyCount }} years"></span>
            @elseif($fyCount > 0)
                <span class="sb-badge">{{ $fyCount }}</span>
            @endif
        </a>

        <a href="{{ route('import.show') }}"
           class="sb-item {{ request()->routeIs('import.*') ? 'active' : '' }}"
           data-label="Import Spreadsheet">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <span class="sb-item-label">Import Spreadsheet</span>
        </a>

        <div class="sb-section">Danger</div>
        <a href="{{ route('db.reset.confirm') }}"
           class="sb-item {{ request()->routeIs('db.reset.*') ? 'active' : '' }}"
           data-label="Reset Database"
           style="color:rgba(252,165,165,.75);">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            <span class="sb-item-label">Reset Database</span>
        </a>
    </nav>

    @auth
        <div class="sb-user">
            <a href="{{ route('profile.show') }}" 
            class="sb-user-link sb-item-tooltip" 
            data-label="Profile Settings">
                @if(Auth::user()->avatar)
                    <img src="{{ Auth::user()->avatar }}" class="sb-user-avatar" alt="">
                @else
                    <div class="sb-user-avatar-fallback">{{ Auth::user()->initials }}</div>
                @endif
                <div class="sb-user-info">
                    <div class="sb-user-name">{{ Auth::user()->name }}</div>
                    <div class="sb-user-email">{{ Auth::user()->email }}</div>
                </div>
            </a>
            <form method="POST" action="{{ route('auth.logout') }}">
                @csrf
                <button type="submit" class="sb-logout sb-item-tooltip" data-label="Sign Out">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    <span class="sb-logout-label">Sign Out</span>
                </button>
            </form>
        </div>

        {{-- ── Check for updates ────────────────────────────────── --}}
        <div class="sb-section">Updates</div>
        <a type="button" id="check-updates-btn" data-url="{{ route('updates.check') }}"
           class="sb-item"
           data-label="Check for Updates">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"> <polyline points="23 4 23 10 17 10"/> <polyline points="1 20 1 14 7 14"/> <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/> </svg>
            <span class="sb-item-label">Check for Updates</span>
        </a>
    @endauth

    <div class="sb-footer">&copy; {{ date('Y') }} {{ $appName }}</div>
</aside>

{{-- ══ Main ════════════════════════════════════════════════════════ --}}
<div class="main" id="main-content">

    {{-- Topbar: drag region for the frameless window. --}}
    <header class="topbar" style="-webkit-app-region: drag; overflow: visible;">

        <div class="topbar-left" style="-webkit-app-region: no-drag;">
            <div class="topbar-title">@yield('title', 'Dashboard')</div>
        </div>

        <div class="topbar-right" style="-webkit-app-region: no-drag;">

            {{-- Per-page actions (buttons, selects, etc.) --}}
            <div style="display:flex; align-items:center; gap:8px;">
                @yield('topbar-actions')
            </div>

            {{-- ── Window controls ──────────────────────────────── --}}
            <div class="wc-buttons">

                {{-- Minimize: wide rounded dash --}}
                <button class="wc-btn wc-minimize" id="min-btn" title="Minimize">
                    <svg width="14" height="2" viewBox="0 0 14 2" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="14" height="2" rx="1" fill="currentColor"/>
                    </svg>
                </button>

                {{-- Maximize / Restore --}}
                <button class="wc-btn wc-maximize" id="max-btn" title="Maximize">
                    {{-- Maximize: rounded square outline --}}
                    <svg class="icon-maximize" width="13" height="13" viewBox="0 0 13 13" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="1" y="1" width="11" height="11" rx="2" stroke="currentColor" stroke-width="1.6" fill="none"/>
                    </svg>
                    {{-- Restore: two overlapping rounded squares --}}
                    <svg class="icon-restore" width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="0.8" y="3.8" width="9.4" height="9.4" rx="1.8" stroke="currentColor" stroke-width="1.5" fill="none"/>
                        <path d="M3.8 3.8V2C3.8 1.338 4.338 0.8 5 0.8H12C12.662 0.8 13.2 1.338 13.2 2V9C13.2 9.662 12.662 10.2 12 10.2H10.2"
                              stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>

                {{-- Close: heavier × that pops on hover --}}
                <button class="wc-btn wc-close" id="close-btn" title="Close">
                    <svg width="13" height="13" viewBox="0 0 13 13" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <line x1="1.5" y1="1.5" x2="11.5" y2="11.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                        <line x1="11.5" y1="1.5" x2="1.5"  y2="11.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                    </svg>
                </button>

            </div><!-- /.wc-buttons -->
        </div>
    </header>

    <div class="content">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-error">
                @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
            </div>
        @endif
        @yield('content')
    </div>
</div>

@stack('scripts')
<script>
(function () {
    // ── Sidebar toggle ────────────────────────────────────────────
    const toggleBtn = document.getElementById('sb-toggle-btn');

    // Check cookie on load
    if (document.cookie.split(';').some(c => c.trim() === 'sb_collapsed=1')) {
        document.body.classList.add('sb-collapsed');
    }

    toggleBtn?.addEventListener('click', function () {
        document.body.classList.toggle('sb-collapsed');
        const collapsed = document.body.classList.contains('sb-collapsed');
        document.cookie = 'sb_collapsed=' + (collapsed ? '1' : '0') + ';path=/;max-age=31536000';
    });

    // ── Window controls ───────────────────────────────────────────
    // Ensure we grab the token from the meta tag
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function wc(action) {
        return fetch('/window/control/' + action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });
    }

    const maxBtn = document.getElementById('max-btn');
    const minBtn = document.getElementById('min-btn');
    const closeBtn = document.getElementById('close-btn');

    let isMaximized = false;

    function updateMaxBtnUI(maximized) {
        isMaximized = maximized;
        if (maximized) {
            maxBtn.classList.add('is-maximized');
            maxBtn.title = 'Restore Down';
        } else {
            maxBtn.classList.remove('is-maximized');
            maxBtn.title = 'Maximize';
        }
    }

    if (minBtn) {
        minBtn.addEventListener('click', () => wc('minimize'));
    }

    if (maxBtn) {
        maxBtn.addEventListener('click', () => {
            // Toggle UI immediately for responsiveness
            updateMaxBtnUI(!isMaximized);
            // Tell PHP to toggle the window
            wc('maximize'); 
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', () => wc('close'));
    }

    // ── Check for Updates button ──────────────────────────────────
    const updateBtn = document.getElementById('check-updates-btn');
    if (updateBtn) {
        const updateUrl  = updateBtn.dataset.url;
        const iconEl     = updateBtn.querySelector('.sb-update-icon');
        const labelEl    = updateBtn.querySelector('.sb-update-label');

        const SPINNER_SVG = `<svg class="sb-update-icon sb-update-spin" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>`;
        const CHECK_SVG   = `<svg class="sb-update-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>`;
        const DEFAULT_SVG = iconEl?.outerHTML || '';

        let checking = false;

        updateBtn.addEventListener('click', async () => {
            if (checking) return;
            checking = true;
            updateBtn.disabled = true;
            updateBtn.classList.add('sb-update-btn--checking');

            // Swap to spinner
            if (iconEl) iconEl.outerHTML = SPINNER_SVG;
            if (labelEl) labelEl.textContent = 'Checking…';

            try {
                const resp = await fetch(updateUrl, {
                    method : 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept'      : 'application/json',
                    },
                });

                const data = await resp.json();

                // Brief success state
                const iconSlot = updateBtn.querySelector('.sb-update-icon, .sb-update-spin');
                if (iconSlot) iconSlot.outerHTML = CHECK_SVG;
                if (labelEl)  labelEl.textContent = 'Checking…';

                setTimeout(() => {
                    // Reset
                    const slot = updateBtn.querySelector('.sb-update-icon, .sb-update-spin');
                    if (slot) slot.outerHTML = DEFAULT_SVG;
                    if (labelEl) labelEl.textContent = 'Check for Updates';
                    updateBtn.disabled = false;
                    updateBtn.classList.remove('sb-update-btn--checking');
                    checking = false;
                }, 3000);

            } catch (err) {
                // Reset on network failure
                const slot = updateBtn.querySelector('.sb-update-icon, .sb-update-spin');
                if (slot) slot.outerHTML = DEFAULT_SVG;
                if (labelEl) labelEl.textContent = 'Check for Updates';
                updateBtn.disabled = false;
                updateBtn.classList.remove('sb-update-btn--checking');
                checking = false;
            }
        });
    }
})();
</script>
</body>
</html>