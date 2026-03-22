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
    @php
        $appName     = \App\Models\AppSetting::appName();
        $appSub      = \App\Models\AppSetting::appSubtitle();
        $sbColor     = \App\Models\AppSetting::get('sidebar_color', '#1a3a2a');
        $sbCollapsed = request()->cookie('sb_collapsed') === '1';
        // precompute counts for badges
        $fyCount  = \App\Models\FinancialYear::count();
        $memCount = \App\Models\Member::count();
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
        html{font-size:15px;-webkit-font-smoothing:antialiased;}
        body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--ink);display:flex;min-height:100vh;}

        /* ── Sidebar ──────────────────────────────────────────── */
        .sidebar{
            width:var(--sb-w);background:var(--forest);color:#fff;
            display:flex;flex-direction:column;
            position:fixed;top:0;left:0;bottom:0;z-index:200;
            transition:width .22s cubic-bezier(.4,0,.2,1);
            overflow:hidden;
        }
        /* --sb-w on body so .main margin-left also updates */
        body.sb-collapsed{ --sb-w:60px; }

        /* Brand */
        .sb-brand{
            padding:20px 16px 16px;
            border-bottom:1px solid rgba(255,255,255,.09);
            display:flex;align-items:center;gap:10px;
            min-height:64px;flex-shrink:0;
            overflow:hidden;
        }
        .sb-brand-text{ flex:1;min-width:0;transition:opacity .15s; }
        .sb-brand h1{font-family:'DM Serif Display',serif;font-size:1.15rem;line-height:1.25;color:var(--mist);white-space:nowrap;}
        .sb-brand span{display:block;font-size:.63rem;font-weight:500;text-transform:uppercase;letter-spacing:.13em;color:var(--sage);margin-top:2px;white-space:nowrap;}
        body.sb-collapsed .sidebar .sb-brand-text{ opacity:0;pointer-events:none; }

        /* Toggle button */
        .sb-toggle{
            flex-shrink:0;width:26px;height:26px;border-radius:6px;
            background:rgba(255,255,255,.08);border:none;
            color:rgba(255,255,255,.55);cursor:pointer;
            display:flex;align-items:center;justify-content:center;
            transition:background .13s,transform .22s;
        }
        .sb-toggle:hover{ background:rgba(255,255,255,.16);color:#fff; }
        body.sb-collapsed .sidebar .sb-toggle{ transform:rotate(180deg); }

        /* Nav */
        .sb-nav{flex:1;padding:10px 0;overflow-y:auto;overflow-x:hidden;}
        .sb-nav::-webkit-scrollbar{width:3px;}
        .sb-nav::-webkit-scrollbar-thumb{background:rgba(255,255,255,.15);border-radius:3px;}
        .sb-section{
            font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.15em;
            color:rgba(255,255,255,.28);padding:10px 20px 3px;
            white-space:nowrap;overflow:hidden;transition:opacity .15s;
        }
        body.sb-collapsed .sidebar .sb-section{ opacity:0; }

        .sb-item{
            display:flex;align-items:center;gap:9px;
            padding:10px 20px;
            color:rgba(255,255,255,.62);text-decoration:none;
            font-size:.855rem;font-weight:500;
            border-left:3px solid transparent;
            transition:all .13s;
            white-space:nowrap;overflow:hidden;
            position:relative;
        }
        .sb-item:hover{ color:#fff;background:rgba(255,255,255,.07); }
        .sb-item.active{ color:var(--mist);background:rgba(82,183,136,.14);border-left-color:var(--sage); }
        .sb-item svg{ flex-shrink:0;width:16px;height:16px;opacity:.75; }
        .sb-item-label{ transition:opacity .15s; }
        body.sb-collapsed .sidebar .sb-item-label{ opacity:0; }

        /* Tooltip when collapsed */
        body.sb-collapsed .sidebar .sb-item::after{
            content:attr(data-label);
            position:absolute;left:62px;
            background:#1c1c1e;color:#fff;
            padding:4px 10px;border-radius:6px;
            font-size:.8rem;font-weight:500;
            white-space:nowrap;pointer-events:none;
            opacity:0;transition:opacity .1s;
            z-index:999;box-shadow:0 4px 12px rgba(0,0,0,.25);
        }
        body.sb-collapsed .sidebar .sb-item:hover::after{ opacity:1; }

        /* ── Nav badges ───────────────────────────────────────── */
        .sb-badge{
            margin-left:auto;flex-shrink:0;
            font-size:.68rem;font-weight:700;
            min-width:20px;height:18px;padding:0 5px;
            border-radius:100px;
            background:rgba(255,255,255,.15);
            color:rgba(255,255,255,.8);
            display:inline-flex;align-items:center;justify-content:center;
            transition:opacity .15s;
        }
        body.sb-collapsed .sidebar .sb-badge{ opacity:0; }

        /* Blinking dot for count > 100 */
        .sb-dot{
            margin-left:auto;flex-shrink:0;
            width:8px;height:8px;border-radius:50%;
            background:#52b788;
            animation:sb-blink 1.4s ease-in-out infinite;
            transition:opacity .15s;
        }
        body.sb-collapsed .sidebar .sb-dot{ opacity:0; }
        @keyframes sb-blink{
            0%,100%{ opacity:1;transform:scale(1); }
            50%{ opacity:.35;transform:scale(.7); }
        }

        /* ── User block + footer ──────────────────────────────── */
        .sb-user{
            border-top:1px solid rgba(255,255,255,.09);
            padding:10px 12px;flex-shrink:0;overflow:hidden;
        }
        .sb-user-link{
            display:flex;align-items:center;gap:9px;
            text-decoration:none;padding:7px 8px;
            border-radius:8px;transition:background .13s;
            margin-bottom:6px;
        }
        .sb-user-link:hover{ background:rgba(255,255,255,.07); }
        .sb-user-avatar{
            width:28px;height:28px;border-radius:50%;
            flex-shrink:0;object-fit:cover;
        }
        .sb-user-avatar-fallback{
            width:28px;height:28px;border-radius:50%;
            background:var(--sage);color:var(--forest);
            display:flex;align-items:center;justify-content:center;
            font-size:.65rem;font-weight:700;flex-shrink:0;
        }
        .sb-user-info{ min-width:0;transition:opacity .15s; }
        .sb-user-name{ font-size:.78rem;font-weight:500;color:rgba(255,255,255,.85);white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
        .sb-user-email{ font-size:.65rem;color:rgba(255,255,255,.38);white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
        body.sb-collapsed .sidebar .sb-user-info{ opacity:0; }
        .sb-logout{
            width:100%;display:flex;align-items:center;gap:8px;
            padding:7px 8px;
            background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09);
            border-radius:6px;color:rgba(255,255,255,.55);
            font-size:.78rem;cursor:pointer;font-family:inherit;transition:all .13s;
            white-space:nowrap;overflow:hidden;
        }
        .sb-logout:hover{ background:rgba(255,255,255,.11);color:#fff; }
        .sb-logout svg{ flex-shrink:0; }
        .sb-logout-label{ transition:opacity .15s; }
        body.sb-collapsed .sidebar .sb-logout-label{ opacity:0; }

        .sb-footer{
            padding:10px 20px;border-top:1px solid rgba(255,255,255,.06);
            font-size:.7rem;color:rgba(255,255,255,.22);
            white-space:nowrap;overflow:hidden;transition:opacity .15s;
            flex-shrink:0;
        }
        body.sb-collapsed .sidebar .sb-footer{ opacity:0; }

        /* ── Main content area ────────────────────────────────── */
        .main{
            margin-left:var(--sb-w);flex:1;
            display:flex;flex-direction:column;min-height:100vh;
            transition:margin-left .22s cubic-bezier(.4,0,.2,1);
        }
        .topbar{
            background:var(--white);border-bottom:1px solid var(--border);
            padding:0 28px;height:56px;
            display:flex;align-items:center;justify-content:space-between;
            position:sticky;top:0;z-index:100;box-shadow:var(--shadow-sm);
        }
        .topbar-left{ display:flex;align-items:center;gap:12px; }
        .topbar-title{ font-family:'DM Serif Display',serif;font-size:1.05rem;color:var(--forest); }
        .topbar-right{ display:flex;align-items:center;gap:10px;flex-shrink:0; }
        .content{ padding:28px;flex:1; }

        /* ── Cards ────────────────────────────────────────────── */
        .card{background:var(--white);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--shadow-sm);}
        .card-head{padding:16px 20px 12px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;}
        .card-title{font-family:'DM Serif Display',serif;font-size:.98rem;color:var(--forest);}
        .card-body{padding:20px;}
        .card-foot{padding:12px 20px;border-top:1px solid var(--border);background:var(--surface);border-radius:0 0 var(--r) var(--r);}

        /* ── Stats ────────────────────────────────────────────── */
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(175px,1fr));gap:14px;margin-bottom:24px;}
        .stat{background:var(--white);border-radius:var(--r);border:1px solid var(--border);padding:18px 20px;box-shadow:var(--shadow-sm);}
        .stat.dark{background:var(--forest);border-color:var(--forest);}
        .stat.green{background:var(--mist);border-color:var(--mint);}
        .stat.red{background:#fee2e2;border-color:#fca5a5;}
        .stat-label{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--mid);margin-bottom:7px;}
        .stat.dark .stat-label{color:var(--sage);}
        .stat.green .stat-label{color:var(--leaf);}
        .stat.red .stat-label{color:#991b1b;}
        .stat-value{font-family:'DM Serif Display',serif;font-size:1.65rem;color:var(--ink);line-height:1;}
        .stat.dark .stat-value{color:var(--white);}
        .stat.green .stat-value{color:var(--forest);}
        .stat.red .stat-value{color:var(--rust);}
        .stat-sub{font-size:.75rem;color:var(--mid);margin-top:4px;}
        .stat.dark .stat-sub{color:rgba(255,255,255,.45);}

        /* ── Table ────────────────────────────────────────────── */
        .tbl-wrap{overflow-x:auto;}
        table{width:100%;border-collapse:collapse;font-size:.855rem;}
        thead th{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--mid);padding:10px 14px;border-bottom:2px solid var(--border);text-align:left;white-space:nowrap;background:var(--surface);}
        tbody td{padding:10px 14px;border-bottom:1px solid var(--border);color:var(--ink);vertical-align:middle;}
        tbody tr:last-child td{border-bottom:none;}
        tbody tr:hover td{background:#fafcfb;}
        .num{font-variant-numeric:tabular-nums;font-weight:500;}
        .pos{color:var(--leaf);}
        .neg{color:var(--rust);}
        .dim{color:var(--mid);}

        /* ── Badges ───────────────────────────────────────────── */
        .badge{display:inline-flex;align-items:center;padding:2px 9px;border-radius:100px;font-size:.75rem;font-weight:600;white-space:nowrap;}
        .badge-g{background:var(--mist);color:#166534;}
        .badge-r{background:#fee2e2;color:#991b1b;}
        .badge-a{background:#fef3c7;color:#92400e;}
        .badge-b{background:#dbeafe;color:#1e40af;}
        .badge-mid{background:var(--surface);color:var(--mid);border:1px solid var(--border);}

        /* ── Buttons ──────────────────────────────────────────── */
        .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:var(--r-sm);font-size:.875rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .15s;font-family:inherit;}
        .btn-primary{background:var(--leaf);color:#fff;}
        .btn-primary:hover{background:var(--forest);}
        .btn-danger{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;}
        .btn-danger:hover{background:#fca5a5;}
        .btn-outline{background:transparent;border:1.5px solid var(--border);color:var(--ink);}
        .btn-outline:hover{border-color:var(--leaf);color:var(--leaf);}
        .btn-ghost{background:transparent;border:none;color:var(--mid);padding:6px 10px;}
        .btn-ghost:hover{color:var(--ink);background:var(--surface);}
        .btn-sm{padding:6px 12px;font-size:.8rem;}
        .btn-xs{padding:4px 9px;font-size:.75rem;}

        /* ── Forms ────────────────────────────────────────────── */
        .form-group{margin-bottom:18px;}
        .form-label{display:block;font-size:.78rem;font-weight:600;color:var(--mid);margin-bottom:6px;text-transform:uppercase;letter-spacing:.06em;}
        .form-control{width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-sm);font-size:.9rem;font-family:inherit;background:var(--white);color:var(--ink);transition:border-color .15s,box-shadow .15s;}
        .form-control:focus{outline:none;border-color:var(--sage);box-shadow:0 0 0 3px rgba(82,183,136,.15);}
        select.form-control{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:36px;}
        textarea.form-control{resize:vertical;min-height:80px;}
        .form-hint{font-size:.78rem;color:var(--soft);margin-top:4px;}
        .form-error{font-size:.78rem;color:var(--rust);margin-top:4px;}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
        .form-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;}
        .checkbox-label{display:flex;align-items:center;gap:9px;font-size:.9rem;cursor:pointer;}
        .checkbox-label input{width:17px;height:17px;accent-color:var(--leaf);}

        /* ── Alerts ───────────────────────────────────────────── */
        .alert{padding:13px 16px;border-radius:var(--r-sm);font-size:.875rem;margin-bottom:20px;border-left:3px solid;}
        .alert-success{background:var(--mist);color:var(--forest);border-color:var(--sage);}
        .alert-error{background:#fee2e2;color:#991b1b;border-color:var(--rust);}
        .alert-info{background:#dbeafe;color:#1e40af;border-color:#3b82f6;}

        /* ── Layout helpers ───────────────────────────────────── */
        .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
        .grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;}
        .flex{display:flex;}.items-center{align-items:center;}.justify-between{justify-content:space-between;}
        .gap-2{gap:8px;}.gap-3{gap:12px;}.gap-4{gap:16px;}
        .mb-4{margin-bottom:16px;}.mb-6{margin-bottom:24px;}.mt-4{margin-top:16px;}
        .w-full{width:100%;}
        .text-sm{font-size:.8rem;}.text-mid{color:var(--mid);}.text-forest{color:var(--forest);}
        .font-serif{font-family:'DM Serif Display',serif;}
        .truncate{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}

        /* ── Pagination ───────────────────────────────────────── */
        .pagination{display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
        .pagination a,.pagination span{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 10px;border-radius:var(--r-sm);font-size:.8rem;font-weight:500;text-decoration:none;color:var(--mid);border:1px solid var(--border);transition:all .12s;}
        .pagination a:hover{color:var(--leaf);border-color:var(--leaf);}
        .pagination .active span{background:var(--leaf);color:#fff;border-color:var(--leaf);}
        .pagination .disabled span{opacity:.4;cursor:default;}

        /* ── Progress bar ─────────────────────────────────────── */
        .progress{height:6px;background:var(--mist);border-radius:3px;overflow:hidden;}
        .progress-bar{height:100%;background:var(--sage);border-radius:3px;transition:width .3s;}

        /* ── Avatar ───────────────────────────────────────────── */
        .avatar{width:40px;height:40px;border-radius:50%;background:var(--forest);color:var(--mist);display:flex;align-items:center;justify-content:center;font-size:.9rem;font-weight:600;flex-shrink:0;}
        .avatar-sm{width:30px;height:30px;font-size:.72rem;}
        .avatar-lg{width:52px;height:52px;font-size:1.1rem;}

        /* ── Month pill ───────────────────────────────────────── */
        .mpill{display:inline-block;padding:3px 10px;border-radius:100px;font-size:.75rem;font-weight:600;}
        .mpill-paid{background:var(--mist);color:#166534;}
        .mpill-unpaid{background:var(--surface);color:var(--soft);border:1px solid var(--border);}

        /* ── Modal ────────────────────────────────────────────── */
        .modal-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;}
        .modal-backdrop.open{display:flex;}
        .modal{background:var(--white);border-radius:var(--r);width:100%;max-width:520px;box-shadow:0 20px 60px rgba(0,0,0,.2);margin:20px;}
        .modal-head{padding:18px 22px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
        .modal-title{font-family:'DM Serif Display',serif;font-size:1rem;color:var(--forest);}
        .close-btn{background:none;border:none;font-size:1.1rem;color:var(--mid);cursor:pointer;padding:2px 6px;border-radius:4px;}
        .close-btn:hover{background:var(--surface);}
        .modal-body{padding:20px 22px;}
        .modal-foot{padding:14px 22px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end;}

        /* ── Empty state ──────────────────────────────────────── */
        .empty-state{text-align:center;padding:60px 20px;color:var(--mid);}
        .empty-state svg{margin:0 auto 14px;display:block;opacity:.25;}
        .empty-state h3{font-family:'DM Serif Display',serif;font-size:1.1rem;color:var(--forest);margin-bottom:6px;}
        .empty-state p{font-size:.875rem;}
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
