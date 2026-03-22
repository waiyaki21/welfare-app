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
<button onclick="document.getElementById('importModal').classList.add('open')" class="btn btn-primary btn-sm" style="white-space:nowrap;flex-shrink:0;">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
    Import
</button>
@endsection

@section('content')

{{-- Import results (shown after a successful import redirect) --}}
@if(session('import_results'))
@php $ir = session('import_results'); @endphp
<div class="card mb-6" style="border-left:3px solid var(--sage);">
    <div class="card-head">
        <div class="card-title" style="color:var(--forest)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:6px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            Import Complete
        </div>
        <div class="flex gap-3 text-sm" style="color:var(--mid);">
            <span><strong style="color:var(--ink)">{{ $ir['sheets_processed'] ?? 0 }}</strong> sheet(s)</span>
            <span><strong style="color:var(--ink)">{{ $ir['members_created'] ?? 0 }}</strong> new members</span>
            <span><strong style="color:var(--ink)">{{ $ir['members_updated'] ?? 0 }}</strong> updated</span>
            <span><strong style="color:var(--ink)">{{ $ir['payments_created'] ?? 0 }}</strong> payments</span>
            <span><strong style="color:var(--ink)">{{ $ir['expenses_created'] ?? 0 }}</strong> expenses</span>
        </div>
    </div>
    @if(!empty($ir['errors']))
    <div class="card-body" style="padding:12px 20px;">
        <div class="text-sm" style="color:var(--rust);font-weight:600;margin-bottom:6px;">Warnings</div>
        @foreach($ir['errors'] as $err)
            <div class="text-sm" style="color:var(--mid);padding:2px 0;">• {{ $err }}</div>
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
            <div class="card-title">Monthly Collections — {{ $selectedYear }}</div>
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
            <div class="card-title">Top Members — Investment</div>
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
        <div class="card-title" style="color:var(--rust)">⚠ Members in Deficit — {{ $selectedYear }}</div>
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
                <td class="num {{ $c > 0 ? 'pos' : 'dim' }}">{{ $c > 0 ? number_format($c) : '—' }}</td>
                <td class="num {{ $e > 0 ? 'neg' : 'dim' }}">{{ $e > 0 ? number_format($e) : '—' }}</td>
                <td class="num {{ $net >= 0 ? 'pos' : 'neg' }}">{{ $c > 0 || $e > 0 ? number_format($net) : '—' }}</td>
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
    <div class="modal" style="max-width:480px;">
        <div class="modal-head">
            <div class="modal-title">Import Spreadsheet</div>
            <button class="close-btn" onclick="closeImportModal()">✕</button>
        </div>
        <form method="POST" action="{{ route('import.store') }}" enctype="multipart/form-data" id="import-form">
            @csrf
            <div class="modal-body">
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
                    <div style="font-size:.8rem;color:var(--mid);">or click to browse</div>
                    <input type="file" id="file-input" name="spreadsheet"
                           accept=".xlsx,.xls" style="display:none"
                           onchange="handleFileSelect(this)">
                </div>

                <div id="file-info" style="display:none;margin-top:12px;padding:10px 14px;background:var(--mist);border-radius:var(--r-sm);display:none;align-items:center;gap:10px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--leaf)" stroke-width="2" style="flex-shrink:0;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    <span id="file-name" style="font-size:.875rem;font-weight:500;color:var(--forest);flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                    <button type="button" onclick="clearFile()" style="background:none;border:none;color:var(--mid);cursor:pointer;font-size:14px;padding:0 2px;">✕</button>
                </div>

                <div style="margin-top:14px;padding:10px 14px;background:var(--surface);border-radius:var(--r-sm);font-size:.8rem;color:var(--mid);line-height:1.6;">
                    Accepts <strong>.xlsx</strong> files up to 20MB. Each sheet named <em>YEAR XXXX</em> is processed as a separate financial year. Re-importing is safe — existing records are updated in place.
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" onclick="closeImportModal()" class="btn btn-outline">Cancel</button>
                <button type="submit" id="import-btn" class="btn btn-primary" disabled>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Import
                </button>
            </div>
        </form>
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
// ── Import modal ──────────────────────────────────────────────────────────
function closeImportModal() {
    document.getElementById('importModal').classList.remove('open');
}
document.getElementById('importModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeImportModal();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeImportModal();
});

function handleFileSelect(input) {
    if (input.files.length) showFile(input.files[0]);
}
function showFile(file) {
    document.getElementById('drop-label').textContent = 'File ready';
    document.getElementById('drop-zone').style.borderColor = 'var(--sage)';
    document.getElementById('drop-zone').style.background  = 'rgba(82,183,136,.04)';
    document.getElementById('file-name').textContent = file.name;
    document.getElementById('file-info').style.display = 'flex';
    document.getElementById('import-btn').disabled = false;
}
function clearFile() {
    document.getElementById('file-input').value = '';
    document.getElementById('drop-label').textContent = 'Drop your .xlsx file here';
    document.getElementById('drop-zone').style.borderColor = 'var(--border)';
    document.getElementById('drop-zone').style.background  = '';
    document.getElementById('file-info').style.display = 'none';
    document.getElementById('import-btn').disabled = true;
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
    } else {
        handleDragLeave(e);
    }
}

// Show import modal automatically if there were validation errors on the file field
@if($errors->has('spreadsheet'))
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('importModal').classList.add('open');
});
@endif

// Submit button: show loading state
document.getElementById('import-form')?.addEventListener('submit', function() {
    const btn = document.getElementById('import-btn');
    btn.disabled = true;
    btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin .7s linear infinite"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Importing…';
});
</script>
<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>
@endpush
