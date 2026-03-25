@extends('layouts.app')
@section('title', 'Dashboard')

@section('topbar-actions')
<form method="GET" class="flex items-center gap-2" style="flex-shrink:0;padding-top: 12px;padding-bottom: 12px;">
    <label class="text-sm text-mid" style="white-space:nowrap">Year</label>
    <select name="year" onchange="this.form.submit()" class="form-control" style="width:90px;padding:6px 28px 6px 10px;">
        @foreach($years as $yr)
            <option value="{{ $yr }}" {{ $yr == $selectedYear ? 'selected' : '' }}>{{ $yr }}</option>
        @endforeach
    </select>
</form>
@if(\App\Models\AppSetting::monthlyImportEnabled())
<button onclick="document.getElementById('monthlyImportModal').classList.add('open')" class="btn btn-outline btn-sm" style="white-space:nowrap;flex-shrink:0;">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    Import Month
</button>
@endif
@if(\App\Models\AppSetting::yearlyImportEnabled())
<button onclick="document.getElementById('importModal').classList.add('open')" class="btn btn-primary btn-sm" style="white-space:nowrap;flex-shrink:0;">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
    Import Year
</button>
@endif
@endsection

@section('content')
{{-- Monthly import results --}}
@if(session('monthly_import_results'))
@php
    $mr = session('monthly_import_results');
    $ms = $mr['summary'] ?? $mr;
@endphp
<div class="card mb-6" style="border-left:3px solid var(--sage);">
    <div class="card-head">
        <div class="card-title" style="color:var(--forest)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:6px;"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Monthly Import - {{ $mr['month'] ?? '' }} {{ $mr['year'] ?? '' }}
        </div>
        <div class="flex gap-3 text-sm" style="color:var(--mid);">
            <span><strong style="color:var(--leaf)">{{ $ms['payments_created'] ?? 0 }}</strong> payments added</span>
            <span><strong style="color:var(--mid)">{{ $ms['payments_skipped'] ?? 0 }}</strong> skipped (existing)</span>
            <span><strong style="color:var(--leaf)">{{ $ms['welfare_created'] ?? 0 }}</strong> welfare added</span>
            <span><strong style="color:var(--mid)">{{ $ms['welfare_skipped'] ?? 0 }}</strong> skipped (existing)</span>
        </div>
    </div>
    @if(!empty($mr['errors']))
    <div class="card-body" style="padding:12px 20px;">
        <div class="text-sm" style="color:var(--rust);font-weight:600;margin-bottom:6px;">Warnings</div>
        @foreach($mr['errors'] as $err)
            <div class="text-sm" style="color:var(--mid);padding:2px 0;">- {{ $err }}</div>
        @endforeach
    </div>
    @endif
</div>
@endif

{{-- Import results (shown after a successful import redirect) --}}
@if(session('import_feedback') || session('import_results'))
@php
    $ir = session('import_feedback', session('import_results'));
    $summary = $ir['summary'] ?? $ir;
@endphp
<div class="card mb-6" style="border-left:3px solid var(--sage);">
    <div class="card-head">
        <div class="card-title" style="color:var(--forest)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:6px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            Import Complete
        </div>
        <div class="flex gap-3 text-sm" style="color:var(--mid);">
            <span><strong style="color:var(--ink)">{{ $summary['sheets_processed'] ?? 0 }}</strong> sheet(s)</span>
            <span><strong style="color:var(--ink)">{{ $summary['members_created'] ?? 0 }}</strong> new members</span>
            <span><strong style="color:var(--ink)">{{ $summary['members_updated'] ?? 0 }}</strong> updated</span>
            <span><strong style="color:var(--ink)">{{ $summary['payments_created'] ?? 0 }}</strong> payments</span>
            <span><strong style="color:var(--ink)">{{ $summary['expenses_created'] ?? 0 }}</strong> expenses</span>
            <span><strong style="color:var(--ink)">{{ $summary['failed_rows'] ?? 0 }}</strong> failed rows</span>
        </div>
    </div>
    @if(!empty($ir['errors']))
    <div class="card-body" style="padding:12px 20px;">
        <div class="text-sm" style="color:var(--rust);font-weight:600;margin-bottom:6px;">Warnings</div>
        @foreach($ir['errors'] as $err)
            <div class="text-sm" style="color:var(--mid);padding:2px 0;">- {{ $err }}</div>
        @endforeach
    </div>
    @endif
</div>
@endif

@if(!$fy)
    <div class="empty-state">
        <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        <h3>No data yet</h3>
        <p>Import a financial spreadsheet to get started.</p>
        <a href="{{ route('import.show') }}" class="btn btn-primary mt-4">Import Spreadsheet</a>
    </div>
@else

{{-- Stats --}}
<div class="stats-grid">
    <div class="stat dark">
        <div class="stat-label">Members</div>
        <div class="stat-value">{{ number_format($stats['members']) }}</div>
        <div class="stat-sub">in {{ $selectedYear }}</div>
    </div>
    <div class="stat">
        <div class="stat-label">Contributions</div>
        <div class="stat-value">{{ number_format($stats['contrib']) }}</div>
        <div class="stat-sub">KES collected</div>
    </div>
    <div class="stat green">
        <div class="stat-label">Total Welfare</div>
        <div class="stat-value">{{ number_format($stats['welfare']) }}</div>
        <div class="stat-sub">KES disbursed</div>
    </div>
    <div class="stat">
        <div class="stat-label">Expenses</div>
        <div class="stat-value">{{ number_format($stats['expenses']) }}</div>
        <div class="stat-sub">KES operating costs</div>
    </div>
    <div class="stat">
        <div class="stat-label">Investment Pool</div>
        <div class="stat-value">{{ number_format($stats['investment']) }}</div>
        <div class="stat-sub">KES net position</div>
    </div>
    <div class="stat {{ $stats['deficit_cnt'] > 0 ? 'red' : '' }}">
        <div class="stat-label">Members in Deficit</div>
        <div class="stat-value">{{ $stats['deficit_cnt'] }}</div>
        <div class="stat-sub">{{ $stats['surplus_cnt'] }} in surplus</div>
    </div>
    <div class="stat">
        <div class="stat-label">No Payment Yet</div>
        <div class="stat-value">{{ $stats['no_payment'] }}</div>
        <div class="stat-sub">members inactive</div>
    </div>
    @if($fy->welfare_per_member)
    <div class="stat">
        <div class="stat-label">Welfare/Member</div>
        <div class="stat-value">{{ number_format($fy->welfare_per_member) }}</div>
        <div class="stat-sub">KES standard</div>
    </div>
    @endif
</div>

{{-- Charts row --}}
<div class="grid-2 mb-6">
    <div class="card">
        <div class="card-head">
            <div class="card-title">Monthly Collections - {{ $selectedYear }}</div>
        </div>
        <div class="card-body" style="padding:16px;">
            <div style="position:relative;height:200px;"><canvas id="monthlyChart"></canvas></div>
        </div>
    </div>

    @if(count($yearOnYear) > 1)
    <div class="card">
        <div class="card-head">
            <div class="card-title">Year-on-Year Contributions</div>
        </div>
        <div class="card-body" style="padding:16px;">
            <div style="position:relative;height:200px;"><canvas id="yoyChart"></canvas></div>
        </div>
    </div>
    @else
    <div class="card">
        <div class="card-head">
            <div class="card-title">Investment Distribution</div>
        </div>
        <div class="card-body" style="padding:16px;">
            <div style="position:relative;height:200px;"><canvas id="distChart"></canvas></div>
        </div>
    </div>
    @endif
</div>

<div class="grid-2 mb-6">
    {{-- Expense breakdown --}}
    @if(!empty($byCat))
    <div class="card">
        <div class="card-head">
            <div class="card-title">Expenses by Category</div>
            <a href="{{ route('expenses.index', ['year'=>$selectedYear]) }}" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="card-body" style="padding:0;">
            <table>
                <thead><tr><th>Category</th><th>Amount (KES)</th><th style="width:120px">Share</th></tr></thead>
                <tbody>
                @php $expTotal = array_sum($byCat); $catNames = \App\Models\ExpenseCategory::pluck('name','slug')->toArray(); @endphp
                @foreach($byCat as $cat => $amt)
                <tr>
                    <td>{{ $catNames[$cat] ?? ucfirst(str_replace('_',' ',$cat)) }}</td>
                    <td class="num">{{ number_format($amt) }}</td>
                    <td>
                        <div class="flex items-center gap-2">
                            <div class="progress" style="flex:1">
                                <div class="progress-bar" style="width:{{ $expTotal>0 ? round($amt/$expTotal*100) : 0 }}%"></div>
                            </div>
                            <span class="text-sm dim">{{ $expTotal>0 ? round($amt/$expTotal*100,1) : 0 }}%</span>
                        </div>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Top members --}}
    <div class="card">
        <div class="card-head">
            <div class="card-title">Top Members - Investment</div>
            <a href="{{ route('members.index', ['year'=>$selectedYear]) }}" class="btn btn-outline btn-sm">All Members</a>
        </div>
        <div class="card-body" style="padding:0;">
            <table>
                <thead><tr><th>Member</th><th>Investment</th><th>Welfare</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($topMembers as $f)
                <tr>
                    <td>
                        <a href="{{ route('members.show', $f->member) }}" style="color:var(--forest);font-weight:500;text-decoration:none;">
                            {{ $f->member->short_name }}
                        </a>
                    </td>
                    <td class="num pos">{{ number_format($f->total_investment) }}</td>
                    <td class="num">{{ number_format($f->total_welfare) }}</td>
                    <td>
                        @if($f->welfare_owing >= 0)
                            <span class="badge badge-g">Surplus</span>
                        @else
                            <span class="badge badge-r">Deficit</span>
                        @endif
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Deficit alert --}}
@if($deficitMembers->count())
<div class="card mb-6">
    <div class="card-head">
        <div class="card-title" style="color:var(--rust)">Members in Deficit - {{ $selectedYear }}</div>
        <span class="badge badge-r">{{ $deficitMembers->count() }} members</span>
    </div>
    <div class="card-body" style="padding:0;">
        <table>
            <thead><tr><th>Member</th><th>Contributions C/F</th><th>Welfare Received</th><th>Deficit</th></tr></thead>
            <tbody>
            @foreach($deficitMembers as $f)
            <tr>
                <td><a href="{{ route('members.show', $f->member) }}" style="color:var(--forest);font-weight:500;text-decoration:none;">{{ $f->member->name }}</a></td>
                <td class="num">{{ number_format($f->contributions_carried_forward) }}</td>
                <td class="num">{{ number_format($f->total_welfare) }}</td>
                <td class="num neg">{{ number_format(abs($f->welfare_owing)) }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Monthly contributions table --}}
<div class="card">
    <div class="card-head">
        <div class="card-title">Monthly Breakdown</div>
        <a href="{{ route('payments.index', ['year'=>$selectedYear]) }}" class="btn btn-outline btn-sm">View Payments</a>
    </div>
    <div class="card-body" style="padding:0;">
        <table>
            <thead><tr><th>Month</th><th>Contributions (KES)</th><th>Expenses (KES)</th><th>Net</th><th style="width:140px">Share of Year</th></tr></thead>
            <tbody>
            @php
                $yearContrib = array_sum($monthlyTotals);
                $months = \App\Models\Payment::MONTHS;
            @endphp
            @foreach($months as $num => $name)
            @php
                $c = $monthlyTotals[$num] ?? 0;
                $e = $monthlyExpenses[$num] ?? 0;
                $net = $c - $e;
                $pct = $yearContrib > 0 ? round($c / $yearContrib * 100, 1) : 0;
            @endphp
            <tr>
                <td style="font-weight:500">{{ $name }}</td>
                <td class="num {{ $c > 0 ? 'pos' : 'dim' }}">{{ $c > 0 ? number_format($c) : '-' }}</td>
                <td class="num {{ $e > 0 ? 'neg' : 'dim' }}">{{ $e > 0 ? number_format($e) : '-' }}</td>
                <td class="num {{ $net >= 0 ? 'pos' : 'neg' }}">{{ $c > 0 || $e > 0 ? number_format($net) : '-' }}</td>
                <td>
                    <div class="flex items-center gap-2">
                        <div class="progress" style="flex:1">
                            <div class="progress-bar" style="width:{{ $pct }}%"></div>
                        </div>
                        <span class="text-sm dim">{{ $pct }}%</span>
                    </div>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection

{{-- Import Modal --}}
<div class="modal-backdrop" id="importModal">
    <div class="modal" id="yearImportDialog" style="max-width:580px;transition:max-width .25s ease;">
        <div class="modal-head">
            <div class="modal-title">Import Spreadsheet</div>
            <button class="close-btn" onclick="closeImportModal()">X</button>
        </div>
        <form method="POST" action="{{ route('import.store') }}" enctype="multipart/form-data" id="import-form">
            @csrf
            <div class="modal-body" style="display:flex;gap:14px;align-items:stretch;">
                <div id="year-form-section" style="flex:1;min-width:0;">
                    <div id="drop-zone"
                         style="border:2px dashed var(--border);border-radius:var(--r-sm);padding:32px 20px;text-align:center;cursor:pointer;transition:border-color .15s,background .15s;"
                         onclick="document.getElementById('file-input').click()"
                         ondragover="handleDragOver(event)"
                         ondragleave="handleDragLeave(event)"
                         ondrop="handleDrop(event)">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--sage)" stroke-width="1.5" style="margin:0 auto 10px;display:block;">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="17 8 12 3 7 8"/>
                            <line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                        <div id="drop-label" style="font-size:.9rem;font-weight:500;color:var(--ink);margin-bottom:4px;">
                            Drop your .xlsx file here
                        </div>
                        <div style="font-size:.8rem;color:var(--mid);">Auto-preview starts after upload</div>
                        <input type="file" id="file-input" name="spreadsheet"
                               accept=".xlsx,.xls" style="display:none"
                               onchange="handleFileSelect(this)">
                    </div>

                    <div id="file-info" style="display:none;margin-top:12px;padding:10px 14px;background:var(--mist);border-radius:var(--r-sm);align-items:center;gap:10px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--leaf)" stroke-width="2" style="flex-shrink:0;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <span id="file-name" style="font-size:.875rem;font-weight:500;color:var(--forest);flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                        <button type="button" onclick="clearFile()" style="background:none;border:none;color:var(--mid);cursor:pointer;font-size:14px;padding:0 2px;">X</button>
                    </div>

                    <div style="margin-top:14px;padding:10px 14px;background:var(--surface);border-radius:var(--r-sm);font-size:.8rem;color:var(--mid);line-height:1.6;">
                        Accepts <strong>.xlsx</strong> files up to 20MB. Parsing is dynamic and supports varying sheet structures (2022-2026).
                    </div>
                </div>
                <div id="year-preview-section" style="display:none;flex:1.5;min-width:0;border-left:1px solid rgba(255,255,255,.22);padding-left:12px;transition:opacity .2s ease;opacity:0;">
                    <div id="year-preview-placeholder" style="height:100%;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.25);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.35);box-shadow:0 10px 30px rgba(10,20,30,.08);border-radius:14px;padding:18px;text-align:center;color:var(--mid);font-size:.88rem;">
                        No sheet uploaded
                    </div>
                    <div id="year-preview-content" style="display:none;">
                        <div id="year-tabs" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px;"></div>
                        <div id="year-tab-body" style="padding:8px;box-sizing:border-box;"></div>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" onclick="closeImportModal()" class="btn btn-outline">Close</button>
                <button type="submit" id="import-btn" class="btn btn-primary" disabled>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Import
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Monthly Import Modal --}}
<div class="modal-backdrop" id="monthlyImportModal">
    <div class="modal" style="max-width:540px;">
        <div class="modal-head">
            <div class="modal-title">Import Monthly Payments</div>
            <button class="close-btn" onclick="closeMonthlyModal()">X</button>
        </div>
        <div class="modal-body" style="padding-bottom:8px;">

            {{-- Step 1: Choose year/month and download template --}}
            <div style="background:var(--surface);border-radius:var(--r-sm);padding:16px 18px;margin-bottom:18px;border:1px solid var(--border);">
                <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--mid);margin-bottom:12px;">
                    Step 1 - Download Template
                </div>
                <div class="form-row">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Financial Year</label>
                        <select id="tpl-year" class="form-control">
                            @foreach($years as $yr)
                            <option value="{{ $yr }}" {{ $yr == $selectedYear ? 'selected' : '' }}>{{ $yr }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Month</label>
                        <select id="tpl-month" class="form-control">
                            @foreach(\App\Models\Payment::MONTHS as $n => $mn)
                            <option value="{{ $n }}" {{ $n == date('n') ? 'selected' : '' }}>{{ $mn }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div style="margin-top:12px;">
                    <button type="button" onclick="downloadTemplate()" class="btn btn-outline btn-sm" style="gap:6px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Download Template for Selected Month
                    </button>
                    <div class="text-sm text-mid" style="margin-top:6px;">
                        Pre-filled with all members. Green cells = existing payment, amber = existing welfare.
                    </div>
                </div>
            </div>

            {{-- Step 2: Upload filled template --}}
            <div style="background:var(--surface);border-radius:var(--r-sm);padding:16px 18px;border:1px solid var(--border);">
                <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--mid);margin-bottom:12px;">
                    Step 2 - Upload Completed Template
                </div>
                <form method="POST" action="{{ route('import.monthly.store') }}" enctype="multipart/form-data" id="monthly-import-form">
                    @csrf
                    <input type="hidden" name="year"  id="upload-year">
                    <input type="hidden" name="month" id="upload-month">

                    <div id="monthly-drop-zone"
                         style="border:2px dashed var(--border);border-radius:var(--r-sm);padding:24px 16px;text-align:center;cursor:pointer;transition:border-color .15s,background .15s;"
                         onclick="document.getElementById('monthly-file-input').click()"
                         ondragover="mHandleDragOver(event)" ondragleave="mHandleDragLeave(event)" ondrop="mHandleDrop(event)">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--sage)" stroke-width="1.5" style="margin:0 auto 8px;display:block;">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                        <div id="monthly-drop-label" style="font-size:.875rem;font-weight:500;color:var(--ink);margin-bottom:3px;">Drop filled template here</div>
                        <div style="font-size:.78rem;color:var(--mid);">or click to browse</div>
                        <input type="file" id="monthly-file-input" name="spreadsheet" accept=".xlsx,.xls" style="display:none" onchange="mHandleFileSelect(this)">
                    </div>

                    <div id="monthly-file-info" style="display:none;margin-top:10px;padding:9px 12px;background:var(--mist);border-radius:var(--r-sm);align-items:center;gap:10px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--leaf)" stroke-width="2" style="flex-shrink:0;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <span id="monthly-file-name" style="font-size:.855rem;font-weight:500;color:var(--forest);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                        <button type="button" onclick="mClearFile()" style="background:none;border:none;color:var(--mid);cursor:pointer;font-size:13px;padding:0 2px;">X</button>
                    </div>

                    <div style="margin-top:12px;padding:10px 12px;background:#fef3c7;border-radius:var(--r-sm);font-size:.78rem;color:#92400e;line-height:1.6;">
                        <strong>Note:</strong> Members with an existing payment or welfare for the selected month will be skipped automatically - no overwriting.
                    </div>

                    <div class="modal-foot" style="padding:14px 0 0;border-top:none;">
                        <button type="button" onclick="closeMonthlyModal()" class="btn btn-outline">Cancel</button>
                        <button type="submit" id="monthly-import-btn" class="btn btn-primary" disabled>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Import Payments
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const green = '#2d6a4f', sage = '#52b788', mist = '#d8f3dc', rust = '#c0392b';

@if($fy)
// Monthly contributions + expenses
const contribs = @json(array_values($monthlyTotals));
const expenses = @json(array_values($monthlyExpenses));

new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: MONTHS,
        datasets: [
            { label: 'Contributions', data: contribs, backgroundColor: mist, borderColor: green, borderWidth: 1.5, borderRadius: 4 },
            { label: 'Expenses',      data: expenses, backgroundColor: '#fee2e266', borderColor: rust, borderWidth: 1.5, borderRadius: 4 },
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { font:{size:11}, boxWidth:12 } },
                   tooltip: { callbacks: { label: c => c.dataset.label + ': KES ' + Math.round(c.raw).toLocaleString() } } },
        scales: {
            x: { grid:{display:false}, ticks:{font:{size:10}} },
            y: { grid:{color:'#f3f4f6'}, ticks:{font:{size:10}, callback: v => v>=1000?(v/1000)+'k':v } }
        }
    }
});

@if(count($yearOnYear) > 1)
const yoy = @json($yearOnYear);
new Chart(document.getElementById('yoyChart'), {
    type: 'bar',
    data: {
        labels: yoy.map(y => y.year),
        datasets: [
            { label: 'Contributions', data: yoy.map(y => y.contrib), backgroundColor: mist, borderColor: green, borderWidth:1.5, borderRadius:4 },
            { label: 'Welfare',       data: yoy.map(y => y.welfare), backgroundColor: '#fef3c7', borderColor:'#d97706', borderWidth:1.5, borderRadius:4 },
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position:'bottom', labels:{ font:{size:11}, boxWidth:12 } },
                   tooltip: { callbacks: { label: c => c.dataset.label+': KES '+Math.round(c.raw).toLocaleString() } } },
        scales: {
            x: { grid:{display:false}, ticks:{font:{size:10}} },
            y: { grid:{color:'#f3f4f6'}, ticks:{font:{size:10}, callback: v => v>=1000?(v/1000)+'k':v } }
        }
    }
});
@else
// Investment distribution donut
const topNames   = @json($topMembers->pluck('member.name')->take(8));
const topInvests = @json($topMembers->pluck('total_investment')->take(8));
const palette = ['#1a3a2a','#2d6a4f','#52b788','#74c69d','#95d5b2','#b7e4c7','#d8f3dc','#e9f5db'];
new Chart(document.getElementById('distChart'), {
    type: 'doughnut',
    data: { labels: topNames, datasets: [{ data: topInvests, backgroundColor: palette, borderWidth: 2, borderColor: '#fff' }] },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position:'right', labels:{ font:{size:10}, boxWidth:10 } },
                   tooltip: { callbacks: { label: c => c.label+': KES '+Math.round(c.raw).toLocaleString() } } }
    }
});
@endif
@endif
</script>

<script>
const YEAR_PREVIEW_URL = '{{ route("imports.year.preview") }}';
const YEAR_FINAL_URL = '{{ route("imports.year.final") }}';
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
const yearState = {
    previewReady: false,
    previewData: null,
    activeTab: 'overview',
    removedMembers: new Set(),
    removedPayments: new Set(),
    removedExpenses: new Set(),
    removedPaymentMonths: new Map(),
    removedExpenseMonths: new Map(),
    paymentMonthItems: new Map(),
    expenseMonthItems: new Map(),
};

function closeImportModal() {
    document.getElementById('importModal').classList.remove('open');
}
document.getElementById('importModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeImportModal();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeImportModal();
});

function showFile(file) {
    document.getElementById('drop-label').textContent = 'File ready';
    document.getElementById('drop-zone').style.borderColor = 'var(--sage)';
    document.getElementById('drop-zone').style.background  = 'rgba(82,183,136,.04)';
    document.getElementById('file-name').textContent = file.name;
    document.getElementById('file-info').style.display = 'flex';
}
function clearFile() {
    document.getElementById('file-input').value = '';
    document.getElementById('drop-label').textContent = 'Drop your .xlsx file here';
    document.getElementById('drop-zone').style.borderColor = 'var(--border)';
    document.getElementById('drop-zone').style.background  = '';
    document.getElementById('file-info').style.display = 'none';
    yearState.previewReady = false;
    yearState.previewData = null;
    yearState.removedMembers.clear();
    yearState.removedPayments.clear();
    yearState.removedExpenses.clear();
    yearState.removedPaymentMonths.clear();
    yearState.removedExpenseMonths.clear();
    yearState.paymentMonthItems.clear();
    yearState.expenseMonthItems.clear();
    renderYearPreviewPlaceholder();
    collapseYearSplit();
    document.getElementById('import-btn').disabled = true;
    document.getElementById('import-btn').innerHTML = 'Import';
}
function expandYearSplit() {
    document.getElementById('yearImportDialog').style.maxWidth = '1080px';
    const section = document.getElementById('year-preview-section');
    section.style.display = 'block';
    setTimeout(() => { section.style.opacity = '1'; }, 30);
}
function collapseYearSplit() {
    document.getElementById('yearImportDialog').style.maxWidth = '580px';
    const section = document.getElementById('year-preview-section');
    section.style.opacity = '0';
    setTimeout(() => { section.style.display = 'none'; }, 220);
}
function renderYearPreviewPlaceholder(text = 'No sheet uploaded') {
    const content = document.getElementById('year-preview-content');
    const placeholder = document.getElementById('year-preview-placeholder');
    content.style.display = 'none';
    placeholder.style.display = 'flex';
    const loading = text.toLowerCase().includes('generating preview');
    const heading = text === 'No sheet uploaded' ? 'No sheet uploaded' : 'Preparing preview';
    placeholder.innerHTML = `<div>${loading ? '<div class="preview-spinner"></div>' : ''}<div style="font-weight:700;margin-bottom:6px;">${escapeHtml(heading)}</div><div style="font-size:.82rem;">${escapeHtml(text)}</div></div>`;
}
function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
function formatKES(value) {
    return `KSH ${Number(value || 0).toLocaleString()}`;
}
function memberRowKey(row) {
    return `${row?.row || ''}|${String(row?.name || '').trim().toLowerCase()}|${row?.phone || ''}`;
}
function paymentItemKey(monthName, item) {
    return `${item?.row || ''}|${String(item?.name || '').trim().toLowerCase()}|${item?.phone || ''}|${String(monthName || '').toUpperCase()}|${Number(item?.amount || 0).toFixed(2)}`;
}
function expenseItemKey(item) {
    return `${item?.row || ''}|${String(item?.category || '').trim().toLowerCase()}|${item?.month || ''}|${Number(item?.amount || 0).toFixed(2)}`;
}
function getVisiblePaymentMonthKeys(monthName, items = []) {
    return items
        .map(item => paymentItemKey(monthName, item))
        .filter(key => !yearState.removedPayments.has(key));
}
function getVisibleExpenseMonthKeys(items = []) {
    return items
        .map(item => expenseItemKey(item))
        .filter(key => !yearState.removedExpenses.has(key));
}
function cachePreviewMonthItems(type, monthName, items = []) {
    if (type === 'payment') {
        yearState.paymentMonthItems.set(monthName, items);
    }
    if (type === 'expense') {
        yearState.expenseMonthItems.set(monthName, items);
    }
    return monthName;
}
function removePreviewItem(type, key) {
    if (type === 'member') yearState.removedMembers.add(key);
    if (type === 'payment') yearState.removedPayments.add(key);
    if (type === 'expense') yearState.removedExpenses.add(key);
    renderYearPreview(yearState.previewData, false);
    activateYearTab(yearState.activeTab);
}
function removePreviewMonth(type, monthName, items = []) {
    const payloadItems = items.length
        ? items
        : (type === 'payment'
            ? (yearState.paymentMonthItems.get(monthName) || [])
            : (yearState.expenseMonthItems.get(monthName) || []));
    if (type === 'payment') {
        const keys = getVisiblePaymentMonthKeys(monthName, payloadItems);
        if (!keys.length) return;
        keys.forEach(key => yearState.removedPayments.add(key));
        yearState.removedPaymentMonths.set(monthName, keys);
    }

    if (type === 'expense') {
        const keys = getVisibleExpenseMonthKeys(payloadItems);
        if (!keys.length) return;
        keys.forEach(key => yearState.removedExpenses.add(key));
        yearState.removedExpenseMonths.set(monthName, keys);
    }

    renderYearPreview(yearState.previewData, false);
    activateYearTab(yearState.activeTab);
}
function undoRemovePreviewMonth(type, monthName) {
    if (type === 'payment') {
        const keys = yearState.removedPaymentMonths.get(monthName) || [];
        keys.forEach(key => yearState.removedPayments.delete(key));
        yearState.removedPaymentMonths.delete(monthName);
    }

    if (type === 'expense') {
        const keys = yearState.removedExpenseMonths.get(monthName) || [];
        keys.forEach(key => yearState.removedExpenses.delete(key));
        yearState.removedExpenseMonths.delete(monthName);
    }

    renderYearPreview(yearState.previewData, false);
    activateYearTab(yearState.activeTab);
}
function setYearLoading(isLoading) {
    const form = document.getElementById('import-form');
    if (!form) return;
    form.style.pointerEvents = isLoading ? 'none' : '';
    form.style.opacity = isLoading ? '.78' : '1';
}
function renderYearPreviewError() {
    const content = document.getElementById('year-preview-content');
    const placeholder = document.getElementById('year-preview-placeholder');
    content.style.display = 'none';
    placeholder.style.display = 'flex';
    placeholder.innerHTML = `
        <div class="preview-error-state">
            <div style="font-weight:700;margin-bottom:6px;">Import Preview Failed</div>
            <div>The spreadsheet could not be read</div>
        </div>
    `;
}
function friendlyErrorMessage(message, name = 'Unknown member', phone = null) {
    const base = String(message || '')
        .replace(/row\s*\d+\s*:\s*/ig, '')
        .replace(/\bDB\b/g, 'database')
        .trim();

    if (!base) {
        return `${name}${phone ? ` - Phone ${phone}` : ''} has an issue in this sheet.`;
    }
    if (base.toLowerCase().includes((name || '').toLowerCase())) {
        return base;
    }
    return `${name}${phone ? ` - Phone ${phone}` : ''} ${base}`.trim();
}
function glassContainer(inner, extraClass = '') {
    return `<div class="preview-glass ${extraClass}">${inner}</div>`;
}
function monthBadgeCount(monthlyInfo) {
    if (!monthlyInfo || !monthlyInfo.months) return 0;
    return Object.values(monthlyInfo.months).filter(m => (m.payments_count || 0) > 0).length;
}
function expenseMonthBadgeCount(expensesInfo) {
    if (!expensesInfo || !expensesInfo.months) return 0;
    return Object.values(expensesInfo.months).filter(m => (m.expenses_count || 0) > 0).length;
}
function createTabButton(id, title, count) {
    const badge = typeof count === 'number'
        ? `<span class="year-tab-badge">${count}</span>`
        : '';
    return `<button type="button" class="year-tab-btn" data-id="${id}"><span>${title}</span>${badge}</button>`;
}
function renderOverviewTab(data) {
    const overview = data.overview || {};
    const rows = [
        ['Total Members', overview.total_members || 0],
        ['Total Contributions', formatKES(overview.total_contributions || 0)],
        ['Total Welfare', formatKES(overview.total_welfare || 0)],
        ['Total Expenses', formatKES(overview.total_expenses || 0)],
        ['Total Payments', formatKES(overview.total_payments || 0)],
    ];

    const grid = `<div class="preview-summary-grid">${rows.map(([label, value]) => `
        <div class="preview-summary-item">
            <div class="preview-summary-label">${escapeHtml(label)}</div>
            <div class="preview-summary-value">${escapeHtml(value)}</div>
        </div>
    `).join('')}</div>`;
    return glassContainer(grid, 'preview-overview');
}
function renderMembersTab(data) {
    const membersInfo = data.members || data.members_info || {};
    const rows = (membersInfo.members || []).filter(row => !yearState.removedMembers.has(memberRowKey(row)));
    const summary = `<div class="preview-members-summary">
        <span>Existing: <strong>${membersInfo.existing_count || 0}</strong></span>
        <span>New: <strong>${membersInfo.new_count || 0}</strong></span>
        <span>Total: <strong>${rows.length || 0}</strong></span>
    </div>`;

    const table = `
        <div class="preview-table-wrap">
            <table class="preview-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th class="num">C/F</th>
                        <th class="num">T.Contributions</th>
                        <th class="num">T.Welfares</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows.map((row, index) => `
                        ${(() => { const key = memberRowKey(row); return `
                        <tr class="${(row.errors || []).length ? 'preview-row-error' : ''}">
                            <td>${index + 1}</td>
                            <td>
                                <div class="member-name" title="${escapeHtml(row.name || '-')}">${escapeHtml(row.name || '-')}</div>
                                <div class="member-phone">${escapeHtml(row.phone || 'No phone')}</div>
                                ${(row.errors || []).length ? `<div class="member-inline-error">${escapeHtml((row.errors || []).join(' '))}</div>` : ''}
                                <button type="button" class="preview-remove-btn" onclick='removePreviewItem("member", ${JSON.stringify(key)})'>X</button>
                            </td>
                            <td class="num">${Number(row.contributions_carried_forward || 0).toLocaleString()}</td>
                            <td class="num">${Number(row.total_contributions || 0).toLocaleString()}</td>
                            <td class="num">${Number(row.total_welfare || 0).toLocaleString()}</td>
                        </tr>
                        `; })()}
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;

    return glassContainer(summary + table, 'preview-members');
}
function renderPaymentCards(data) {
    const payments = data.payments || data.monthlyPayments_info || { months: {} };
    const monthEntries = Object.entries(payments.months || {})
        .map(([name, month]) => {
            const items = (month.items || []).filter(item => !yearState.removedPayments.has(paymentItemKey(name, item)));
            const deleted = yearState.removedPaymentMonths.has(name);
            return [name, { ...month, items, deleted, payments_count: items.length, total_amount: items.reduce((s, i) => s + Number(i.amount || 0), 0) }];
        });
    const visibleEntries = monthEntries.filter(([, month]) => month.deleted || (month.payments_count || 0) > 0);
    if (!visibleEntries.length) {
        return glassContainer('<div class="preview-empty">No monthly payments detected.</div>', 'preview-payments preview-tab-scroll');
    }

    return `<div class="preview-card-grid preview-tab-scroll">${visibleEntries.map(([monthName, info]) => `
        ${info.deleted ? `
            <div class="preview-glass preview-month-card preview-month-card-deleted">
                <div class="preview-month-title">${escapeHtml(monthName.toUpperCase())}</div>
                <div class="preview-month-meta">This month has been removed from preview.</div>
                <button type="button" class="preview-undo-btn" onclick='undoRemovePreviewMonth("payment", ${JSON.stringify(monthName)})'>Undo Delete</button>
            </div>
        ` : `
            <div class="preview-glass preview-month-card">
                <div class="preview-month-head">
                    <div>
                        <div class="preview-month-title">${escapeHtml(monthName.toUpperCase())}</div>
                        <div class="preview-month-meta">Payments: <strong>${info.payments_count || 0}</strong></div>
                        <div class="preview-month-meta">Total: <strong>${formatKES(info.total_amount || 0)}</strong></div>
                    </div>
                    <button type="button" class="preview-month-delete-btn" onclick='removePreviewMonth("payment", ${JSON.stringify(cachePreviewMonthItems("payment", monthName, info.items || []))})'>Delete Month</button>
                </div>
                <div class="preview-month-items">
                    ${(info.items || []).map(item => `
                        <div class="preview-month-item">
                            <span class="name">${escapeHtml(item.name || 'Unknown')}</span>
                            <span class="amount">${formatKES(item.amount || 0)}</span><button type="button" class="preview-remove-btn" onclick='removePreviewItem("payment", ${JSON.stringify(paymentItemKey(monthName, item))})'>X</button>
                        </div>
                    `).join('')}
                </div>
            </div>
        `}
    `).join('')}</div>`;
}
function renderExpenseCards(data) {
    const expenses = data.expenses || data.expenses_info || { months: {}, rows: [] };
    const monthEntries = Object.entries(expenses.months || {})
        .map(([name, month]) => {
            const items = (month.items || []).filter(item => !yearState.removedExpenses.has(expenseItemKey(item)));
            const deleted = yearState.removedExpenseMonths.has(name);
            return [name, { ...month, items, deleted, expenses_count: items.length, total_amount: items.reduce((s, i) => s + Number(i.amount || 0), 0) }];
        });
    const visibleEntries = monthEntries.filter(([, month]) => month.deleted || (month.expenses_count || 0) > 0);
    if (!visibleEntries.length) {
        return glassContainer('<div class="preview-empty">No expenses detected.</div>', 'preview-expenses preview-tab-scroll');
    }

    return `<div class="preview-card-grid preview-tab-scroll">${visibleEntries.map(([monthName, info]) => `
        ${info.deleted ? `
            <div class="preview-glass preview-month-card preview-month-card-deleted">
                <div class="preview-month-title">${escapeHtml(monthName.toUpperCase())}</div>
                <div class="preview-month-meta">This month has been removed from preview.</div>
                <button type="button" class="preview-undo-btn" onclick='undoRemovePreviewMonth("expense", ${JSON.stringify(monthName)})'>Undo Delete</button>
            </div>
        ` : `
            <div class="preview-glass preview-month-card">
                <div class="preview-month-head">
                    <div>
                        <div class="preview-month-title">${escapeHtml(monthName.toUpperCase())}</div>
                        <div class="preview-month-meta">Expenses: <strong>${info.expenses_count || 0}</strong></div>
                        <div class="preview-month-meta">Total: <strong>${formatKES(info.total_amount || 0)}</strong></div>
                    </div>
                    <button type="button" class="preview-month-delete-btn" onclick='removePreviewMonth("expense", ${JSON.stringify(cachePreviewMonthItems("expense", monthName, info.items || []))})'>Delete Month</button>
                </div>
                <div class="preview-month-items">
                    ${(info.items || []).map(item => `
                        <div class="preview-month-item">
                            <span class="name">${escapeHtml(item.category || 'Expense')}</span>
                            <span class="amount">${formatKES(item.amount || 0)}</span><button type="button" class="preview-remove-btn" onclick='removePreviewItem("expense", ${JSON.stringify(expenseItemKey(item))})'>X</button>
                        </div>
                    `).join('')}
                </div>
            </div>
        `}
    `).join('')}</div>`;
}
function renderErrorsTab(data) {
    const membersInfo = data.members || data.members_info || {};
    const genericErrors = data.errors || [];
    const memberErrors = (membersInfo.error_members || []).flatMap((entry) => {
        return (entry.errors || []).map(msg => ({
            title: `${entry.name || 'Unknown member'}${entry.phone ? ` (${entry.phone})` : ''}`,
            message: friendlyErrorMessage(msg, entry.name || 'Unknown member', entry.phone || null),
        }));
    });
    const errors = [
        ...memberErrors,
        ...genericErrors.map(msg => ({
            title: 'General Validation',
            message: friendlyErrorMessage(msg),
        })),
    ];

    if (!errors.length) {
        return glassContainer('<div class="preview-empty">No errors found.</div>', 'preview-errors preview-tab-scroll');
    }

    return `<div class="preview-error-grid preview-tab-scroll">${errors.map(error => `
        <div class="preview-glass preview-error-card">
            <div class="preview-error-head">${escapeHtml(error.title || 'Import Validation')}</div>
            <div class="preview-error-body">${escapeHtml(error.message || '')}</div>
        </div>
    `).join('')}</div>`;
}
function activateYearTab(tabId) {
    yearState.activeTab = tabId;
    const body = document.getElementById('year-tab-body');
    const data = yearState.previewData || {};
    body.className = 'preview-tab-body';
    if (tabId === 'payments' || tabId === 'expenses' || tabId === 'errors') {
        body.classList.add('preview-tab-body-scrollable');
    }
    if (tabId === 'overview') body.innerHTML = renderOverviewTab(data);
    if (tabId === 'members') body.innerHTML = renderMembersTab(data);
    if (tabId === 'payments') body.innerHTML = renderPaymentCards(data);
    if (tabId === 'expenses') body.innerHTML = renderExpenseCards(data);
    if (tabId === 'errors') body.innerHTML = renderErrorsTab(data);

    document.querySelectorAll('.year-tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.id === tabId);
    });
}
function renderYearPreview(payload, resetRemoved = true) {
    if (resetRemoved) {
        yearState.removedMembers.clear();
        yearState.removedPayments.clear();
        yearState.removedExpenses.clear();
        yearState.removedPaymentMonths.clear();
        yearState.removedExpenseMonths.clear();
        yearState.paymentMonthItems.clear();
        yearState.expenseMonthItems.clear();
    }
    yearState.previewData = payload;
    const placeholder = document.getElementById('year-preview-placeholder');
    const content = document.getElementById('year-preview-content');
    const tabs = document.getElementById('year-tabs');
    const payments = payload.payments || payload.monthlyPayments_info || { months: {} };
    const expenses = payload.expenses || payload.expenses_info || { months: {} };
    const members = payload.members || payload.members_info || {};
    const filteredMembersCount = (members.members || []).filter(row => !yearState.removedMembers.has(memberRowKey(row))).length;
    const filteredPaymentMonths = Object.entries(payments.months || {}).filter(([m, month]) => {
        if (yearState.removedPaymentMonths.has(m)) return true;
        const count = (month.items || []).filter(item => !yearState.removedPayments.has(paymentItemKey(m, item))).length;
        return count > 0;
    }).length;
    const filteredExpenseMonths = Object.entries(expenses.months || {}).filter(([name, month]) => {
        if (yearState.removedExpenseMonths.has(name)) return true;
        const count = (month.items || []).filter(item => !yearState.removedExpenses.has(expenseItemKey(item))).length;
        return count > 0;
    }).length;
    const errorCount = (payload.errors || []).length + (members.error_members || []).length;

    placeholder.style.display = 'none';
    content.style.display = 'block';
    tabs.classList.add('preview-tabs');
    tabs.innerHTML = [
        createTabButton('overview', 'Overview'),
        createTabButton('members', 'Members', filteredMembersCount),
        createTabButton('payments', 'Payments', filteredPaymentMonths),
        createTabButton('expenses', 'Expenses', filteredExpenseMonths),
        createTabButton('errors', 'Errors', errorCount),
    ].join('');

    tabs.querySelectorAll('.year-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => activateYearTab(btn.dataset.id));
    });
    activateYearTab(yearState.activeTab || 'overview');
}
async function postForm(url, formData) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        },
        body: formData,
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || 'Request failed');
    return data;
}
async function autoPreviewYearImport() {
    const input = document.getElementById('file-input');
    if (!input?.files?.length) return;

    const formData = new FormData();
    formData.append('spreadsheet', input.files[0]);
    try {
        setYearLoading(true);
        expandYearSplit();
        renderYearPreviewPlaceholder('Generating preview... please wait');
        document.getElementById('import-btn').disabled = true;
        const preview = await postForm(YEAR_PREVIEW_URL, formData);
        renderYearPreview(preview);
        yearState.previewReady = true;
        document.getElementById('import-btn').disabled = false;
        document.getElementById('import-btn').innerHTML = 'Import';
    } catch (error) {
        yearState.previewReady = false;
        document.getElementById('import-btn').disabled = true;
        renderYearPreviewError();
    } finally {
        setYearLoading(false);
    }
}
function handleFileSelect(input) {
    if (input.files.length) {
        showFile(input.files[0]);
        autoPreviewYearImport();
    }
}
function handleDragOver(e) {
    e.preventDefault();
    document.getElementById('drop-zone').style.borderColor = 'var(--sage)';
    document.getElementById('drop-zone').style.background  = 'rgba(82,183,136,.06)';
}
function handleDragLeave(e) {
    document.getElementById('drop-zone').style.borderColor = 'var(--border)';
    document.getElementById('drop-zone').style.background  = '';
}
function handleDrop(e) {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    if (file && (file.name.endsWith('.xlsx') || file.name.endsWith('.xls'))) {
        const dt = new DataTransfer();
        dt.items.add(file);
        document.getElementById('file-input').files = dt.files;
        showFile(file);
        autoPreviewYearImport();
    } else {
        handleDragLeave(e);
    }
}
document.getElementById('import-form')?.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (!yearState.previewReady) return;

    const input = document.getElementById('file-input');
    if (!input?.files?.length) return;

    const formData = new FormData();
    formData.append('spreadsheet', input.files[0]);
    formData.append('removed_members', JSON.stringify(Array.from(yearState.removedMembers)));
    formData.append('removed_payments', JSON.stringify(Array.from(yearState.removedPayments)));
    formData.append('removed_expenses', JSON.stringify(Array.from(yearState.removedExpenses)));
    const importBtn = document.getElementById('import-btn');

    try {
        importBtn.disabled = true;
        importBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin .7s linear infinite"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Importing...';
        const result = await postForm(YEAR_FINAL_URL, formData);
        renderYearPreview(result);
        location.reload();
    } catch (error) {
        alert(error.message || 'Final import failed.');
        importBtn.disabled = false;
        importBtn.innerHTML = 'Import';
    }
});

// Monthly import modal existing behavior
function closeMonthlyModal() {
    document.getElementById('monthlyImportModal').classList.remove('open');
}
document.getElementById('monthlyImportModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeMonthlyModal();
});
function downloadTemplate() {
    const year  = document.getElementById('tpl-year').value;
    const month = document.getElementById('tpl-month').value;
    document.getElementById('upload-year').value  = year;
    document.getElementById('upload-month').value = month;
    const url = '{{ route("import.monthly.template") }}?year=' + year + '&month=' + month;
    window.location.href = url;
}
document.getElementById('tpl-year')?.addEventListener('change', function() {
    document.getElementById('upload-year').value = this.value;
});
document.getElementById('tpl-month')?.addEventListener('change', function() {
    document.getElementById('upload-month').value = this.value;
});
function mHandleFileSelect(input) {
    if (input.files.length) mShowFile(input.files[0]);
}
function mShowFile(file) {
    document.getElementById('monthly-drop-label').textContent = 'File ready';
    document.getElementById('monthly-drop-zone').style.borderColor = 'var(--sage)';
    document.getElementById('monthly-drop-zone').style.background  = 'rgba(82,183,136,.04)';
    document.getElementById('monthly-file-name').textContent = file.name;
    document.getElementById('monthly-file-info').style.display = 'flex';
    document.getElementById('monthly-import-btn').disabled = false;
}
function mClearFile() {
    document.getElementById('monthly-file-input').value = '';
    document.getElementById('monthly-drop-label').textContent = 'Drop filled template here';
    document.getElementById('monthly-drop-zone').style.borderColor = 'var(--border)';
    document.getElementById('monthly-drop-zone').style.background  = '';
    document.getElementById('monthly-file-info').style.display = 'none';
    document.getElementById('monthly-import-btn').disabled = true;
}
function mHandleDragOver(e) {
    e.preventDefault();
    document.getElementById('monthly-drop-zone').style.borderColor = 'var(--sage)';
    document.getElementById('monthly-drop-zone').style.background  = 'rgba(82,183,136,.06)';
}
function mHandleDragLeave(e) {
    document.getElementById('monthly-drop-zone').style.borderColor = 'var(--border)';
    document.getElementById('monthly-drop-zone').style.background  = '';
}
function mHandleDrop(e) {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    if (file && (file.name.endsWith('.xlsx') || file.name.endsWith('.xls'))) {
        const dt = new DataTransfer();
        dt.items.add(file);
        document.getElementById('monthly-file-input').files = dt.files;
        mShowFile(file);
    } else { mHandleDragLeave(e); }
}
document.getElementById('monthly-import-form')?.addEventListener('submit', function() {
    const btn = document.getElementById('monthly-import-btn');
    btn.disabled = true;
    btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin .7s linear infinite"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Importing...';
});

@if($errors->has('spreadsheet'))
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('importModal').classList.add('open');
});
@endif

document.addEventListener('DOMContentLoaded', () => {
    const y = document.getElementById('tpl-year');
    const m = document.getElementById('tpl-month');
    if (y) document.getElementById('upload-year').value = y.value;
    if (m) document.getElementById('upload-month').value = m.value;
    renderYearPreviewPlaceholder();
    document.getElementById('import-btn').disabled = true;
    document.getElementById('import-btn').innerHTML = 'Import';
});
</script>
<style>
@keyframes spin { to { transform: rotate(360deg); } }
#year-preview-section {
    background: linear-gradient(180deg, rgba(255,255,255,.18), rgba(248,250,252,.12));
}
.preview-tab-body {
    padding: 12px;
    box-sizing: border-box;
    background: #f8fafc;
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 14px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.07);
}
.preview-tab-body-scrollable {
    max-height: 250px;
    overflow: hidden;
}
.preview-tab-scroll {
    max-height: 250px;
    overflow-y: auto;
    padding-right: 4px;
    scrollbar-width: thin;
    scrollbar-color: rgba(100, 116, 139, 0.45) transparent;
}
.preview-tab-scroll::-webkit-scrollbar {
    width: 6px;
}
.preview-tab-scroll::-webkit-scrollbar-thumb {
    background: rgba(100, 116, 139, 0.42);
    border-radius: 999px;
}
.preview-tab-scroll::-webkit-scrollbar-track {
    background: transparent;
}
.preview-glass {
    background: rgba(255, 255, 255, 0.28);
    border: 1px solid rgba(255, 255, 255, 0.52);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
    border-radius: 14px;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    transition: box-shadow .16s ease, border-color .16s ease, transform .16s ease;
}
.preview-glass:hover {
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.12);
    border-color: rgba(255, 255, 255, 0.66);
}
.preview-tabs {
    padding: 6px;
    margin-bottom: 10px;
}
.year-tab-btn {
    border: 1px solid rgba(32, 68, 51, 0.18);
    background: rgba(255, 255, 255, 0.45);
    color: var(--forest);
    border-radius: 999px;
    font-size: .78rem;
    font-weight: 600;
    padding: 6px 10px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    transition: all .15s ease;
}
.year-tab-btn:hover {
    border-color: rgba(32, 68, 51, 0.26);
    transform: translateY(-1px);
}
.year-tab-btn.active {
    background: rgba(45, 106, 79, 0.18);
    border-color: rgba(45, 106, 79, 0.32);
}
.year-tab-badge {
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.42);
    color: var(--forest);
    font-size: .70rem;
    line-height: 1;
    padding: 3px 7px;
}
.year-tab-btn.active .year-tab-badge {
    background: rgba(45, 106, 79, 0.16);
    color: rgba(28, 65, 48, .94);
}
.preview-summary-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    padding: 12px;
}
.preview-summary-item {
    background: rgba(255, 255, 255, .36);
    border: 1px solid rgba(255, 255, 255, .5);
    border-radius: 10px;
    padding: 10px;
}
.preview-summary-label {
    font-size: .74rem;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--mid);
}
.preview-summary-value {
    font-size: .95rem;
    font-weight: 700;
    color: var(--ink);
}
.preview-members {
    padding: 10px;
}
.preview-members-summary {
    display: flex;
    gap: 10px;
    font-size: .8rem;
    color: var(--mid);
    margin-bottom: 10px;
}
.preview-table-wrap {
    max-height: 320px;
    overflow: auto;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, .4);
    background: rgba(255, 255, 255, .33);
}
.preview-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .79rem;
}
.preview-table th,
.preview-table td {
    padding: 7px 8px;
    border-bottom: 1px solid rgba(255, 255, 255, .42);
    vertical-align: top;
}
.preview-table th {
    text-align: left;
    color: var(--forest);
    font-size: .73rem;
    letter-spacing: .03em;
    text-transform: uppercase;
    background: rgba(246, 250, 248, .96);
    position: sticky;
    top: 0;
    z-index: 2;
    backdrop-filter: blur(2px);
    -webkit-backdrop-filter: blur(2px);
}
.preview-table .num {
    text-align: right;
    white-space: nowrap;
}
.member-name {
    font-weight: 600;
    color: var(--ink);
    max-width: 160px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.member-phone {
    color: var(--mid);
    font-size: .74rem;
}
.preview-row-error {
    background: rgba(254, 226, 226, 0.55);
}
.member-inline-error {
    margin-top: 4px;
    font-size: .72rem;
    color: #b42318;
}
.preview-card-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 12px;
    padding: 2px 3px 2px 3px;
}
.preview-month-card {
    padding: 10px;
}
.preview-month-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}
.preview-month-card-deleted {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    background: rgba(241, 245, 249, 0.72);
}
.preview-month-title {
    font-size: .78rem;
    font-weight: 800;
    letter-spacing: .05em;
    color: var(--forest);
    margin-bottom: 5px;
}
.preview-month-meta {
    font-size: .77rem;
    color: var(--mid);
}
.preview-month-items {
    margin-top: 8px;
    border-top: 1px solid rgba(255, 255, 255, .48);
    padding-top: 6px;
}
.preview-month-item {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    font-size: .76rem;
    color: var(--ink);
    padding: 3px 0;
}
.preview-month-item .name {
    max-width: 66%;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}
.preview-month-item .amount {
    white-space: nowrap;
}
.preview-remove-btn {
    margin-left: 6px;
    border: 1px solid rgba(180, 20, 20, .3);
    background: rgba(254, 226, 226, .5);
    color: #9f1239;
    border-radius: 999px;
    width: 20px;
    height: 20px;
    font-size: .7rem;
    line-height: 1;
    cursor: pointer;
}
.preview-month-delete-btn,
.preview-undo-btn {
    border-radius: 999px;
    border: 1px solid rgba(32, 68, 51, 0.16);
    background: rgba(255, 255, 255, 0.62);
    color: var(--forest);
    font-size: .72rem;
    font-weight: 700;
    padding: 6px 10px;
    cursor: pointer;
    white-space: nowrap;
}
.preview-month-delete-btn:hover,
.preview-undo-btn:hover {
    background: rgba(255, 255, 255, 0.82);
}
.preview-empty {
    padding: 14px;
    color: var(--mid);
    font-size: .84rem;
}
.preview-error-state {
    width: 100%;
    background: #4a1115;
    border: 1px solid #7f1d1d;
    color: #fecaca;
    border-radius: 12px;
    padding: 14px;
    text-align: left;
}
.preview-spinner {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 2px solid rgba(45,106,79,.25);
    border-top-color: #2d6a4f;
    animation: spin .7s linear infinite;
    margin: 0 auto 8px;
}
.preview-error-grid {
    display: grid;
    gap: 10px;
    padding: 2px 3px 2px 3px;
}
.preview-error-card {
    padding: 11px;
    background: rgba(254, 226, 226, 0.42);
    border-color: rgba(220, 38, 38, 0.28);
}
.preview-error-head {
    font-size: .74rem;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #b42318;
    font-weight: 700;
    margin-bottom: 4px;
}
.preview-error-body {
    font-size: .82rem;
    color: #7a271a;
}
</style>
@endpush
