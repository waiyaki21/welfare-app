<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Athoni Welfare — @yield('title', 'Dashboard')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    {{-- Replace the @if block with just this --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @php
        $appName     = \App\Models\AppSetting::appName();
        $appSub      = \App\Models\AppSetting::appSubtitle();
        $sbColor     = \App\Models\AppSetting::get('sidebar_color', '#1a3a2a');
        $sbCollapsed = request()->cookie('sb_collapsed') === '1';
        
        // Precompute counts and years
        $fyCount     = \App\Models\FinancialYear::count();
        $years       = \App\Models\FinancialYear::orderByDesc('year')->pluck('year');
        
        // 1. Get the selected year from request OR default to the latest year in DB OR current system year
        $selectedYear = (int) request('year', $years->first() ?? date('Y'));

        // 2. Find that specific FinancialYear and get the count of memberFinancials
        // We use withCount() so Laravel performs a "SELECT COUNT" rather than loading all rows
        $yearRecord = \App\Models\FinancialYear::where('year', $selectedYear)
            ->withCount(['memberFinancials', 'expenditures'])
            ->first();

        // 3. Set the memCount (default to 0 if no record exists for that year)
        $memCount = $yearRecord ? $yearRecord->member_financials_count : 0;
        $expCount = $yearRecord ? $yearRecord->expenditures_count : 0;
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
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    </style>
    @stack('styles')
</head>
<body class="{{ request()->cookie('sb_collapsed') === '1' ? 'sb-collapsed' : '' }}">

{{-- ══ Sidebar ════════════════════════════════════════════════════ --}}
<aside class="sidebar" id="sidebar">
    <div class="sb-brand">
        <div class="sb-brand-text">
            <h1>{{ $appName }}</h1>
            @if($appSub)<span>{{ $appSub }}</span>@endif
        </div>
        <button class="sb-toggle" id="sb-toggle-btn" title="Toggle sidebar">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
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

        {{-- Members with badge --}}
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
        </a>

        <a href="{{ route('expenses.index') }}"
           class="sb-item {{ request()->routeIs('expenses.*') ? 'active' : '' }}"
           data-label="Expenses">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            <span class="sb-item-label">Expenses</span>
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

        {{-- Financial Years with badge --}}
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

    {{-- User block --}}
    @auth
    <div class="sb-user">
        <a href="{{ route('profile.show') }}" class="sb-user-link" title="Profile &amp; Settings">
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
            <button type="submit" class="sb-logout">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span class="sb-logout-label">Sign Out</span>
            </button>
        </form>
    </div>
    @endauth

    <div class="sb-footer">&copy; {{ date('Y') }} {{ $appName }}</div>
</aside>

{{-- ══ Main ════════════════════════════════════════════════════════ --}}
<div class="main" id="main-content">
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">@yield('title', 'Dashboard')</div>
        </div>
        <div class="topbar-right">
            @yield('topbar-actions')
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
    const toggleBtn = document.getElementById('sb-toggle-btn');

    // Restore state from cookie — toggle body class, CSS handles everything else
    const isCollapsed = document.cookie.split(';').some(c => c.trim() === 'sb_collapsed=1');
    if (isCollapsed) document.body.classList.add('sb-collapsed');

    toggleBtn?.addEventListener('click', function () {
        document.body.classList.toggle('sb-collapsed');
        const collapsed = document.body.classList.contains('sb-collapsed');
        document.cookie = 'sb_collapsed=' + (collapsed ? '1' : '0') + ';path=/;max-age=31536000';
    });
})();
</script>
</body>
</html>
