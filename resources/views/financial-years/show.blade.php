@extends('layouts.app')
@section('title', 'Year ' . $fy->year)

@section('topbar-actions')
<a href="{{ route('financial-years.export', $fy) }}"
   class="btn btn-primary btn-sm"
   title="Download {{ $fy->year }} ledger as Excel">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
        <polyline points="7 10 12 15 17 10"/>
        <line x1="12" y1="15" x2="12" y2="3"/>
    </svg>
    Export to Excel
</a>
<a href="{{ route('financial-years.edit', $fy) }}" class="btn btn-outline btn-sm">Edit Year</a>
<a href="{{ route('financial-years.index') }}" class="btn btn-ghost btn-sm">← All Years</a>
@endsection

@push('styles')
<style>
th.sortable{cursor:pointer;user-select:none;white-space:nowrap;}
th.sortable:hover{color:var(--forest);}
th.sortable .si{display:inline-flex;flex-direction:column;gap:1px;margin-left:5px;vertical-align:middle;opacity:.3;transition:opacity .12s;}
th.sortable:hover .si{opacity:.65;}
th.sortable.asc .si,th.sortable.desc .si{opacity:1;}
th.sortable.asc .au{opacity:1;}th.sortable.asc .ad{opacity:.2;}
th.sortable.desc .au{opacity:.2;}th.sortable.desc .ad{opacity:1;}
.au,.ad{width:0;height:0;display:block;border-left:4px solid transparent;border-right:4px solid transparent;}
.au{border-bottom:5px solid currentColor;}.ad{border-top:5px solid currentColor;}
</style>
@endpush

@section('content')

{{-- Header card --}}
<div class="card mb-6">
    <div class="card-body">
        <div class="flex items-center gap-4">
            <div style="width:56px;height:56px;border-radius:12px;background:var(--forest);color:var(--mist);display:flex;align-items:center;justify-content:center;font-family:'DM Serif Display',serif;font-size:1.4rem;flex-shrink:0;">
                {{ substr($fy->year, 2) }}
            </div>
            <div style="flex:1;">
                <div style="font-family:'DM Serif Display',serif;font-size:1.35rem;color:var(--forest);">
                    Financial Year {{ $fy->year }}
                    @if($fy->is_current) <span class="badge badge-g" style="margin-left:8px;vertical-align:middle;">Active Year</span> @endif
                </div>
                <div class="text-sm text-mid" style="margin-top:3px;">
                    @if($fy->sheet_name) {{ $fy->sheet_name }} &nbsp;·&nbsp; @endif
                    Welfare per member: <strong>KES {{ number_format($fy->welfare_per_member) }}</strong>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('import.show') }}" class="btn btn-outline btn-sm">Re-import</a>
                <form method="POST" action="{{ route('financial-years.destroy', $fy) }}"
                      onsubmit="return confirm('Delete {{ $fy->year }} and ALL its data? This cannot be undone.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">Delete Year</button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Stats --}}
<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat dark">
        <div class="stat-label">Members</div>
        <div class="stat-value">{{ $stats['members'] }}</div>
        <div class="stat-sub">on record</div>
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
        <div class="stat-label">Net Investment</div>
        <div class="stat-value" style="color:{{ $stats['invest'] >= 0 ? 'var(--leaf)' : 'var(--rust)' }}">
            {{ number_format($stats['invest']) }}
        </div>
        <div class="stat-sub">KES pool</div>
    </div>
    <div class="stat">
        <div class="stat-label">Expenses</div>
        <div class="stat-value" style="color:var(--rust)">{{ number_format($stats['expenses']) }}</div>
        <div class="stat-sub">KES operating</div>
    </div>
    <div class="stat {{ $stats['deficit'] > 0 ? 'red' : '' }}">
        <div class="stat-label">In Deficit</div>
        <div class="stat-value">{{ $stats['deficit'] }}</div>
        <div class="stat-sub">{{ $stats['surplus'] }} in surplus</div>
    </div>
    <div class="stat">
        <div class="stat-label">No Payment</div>
        <div class="stat-value">{{ $stats['no_payment'] }}</div>
        <div class="stat-sub">inactive members</div>
    </div>
</div>

{{-- Charts --}}
<div class="grid-2 mb-6">
    <div class="card">
        <div class="card-head"><div class="card-title">Monthly Collections</div></div>
        <div class="card-body" style="padding:14px;">
            <div style="position:relative;height:200px;"><canvas id="monthlyChart"></canvas></div>
        </div>
    </div>

    @if(!empty($expensesByCat))
    <div class="card">
        <div class="card-head"><div class="card-title">Expenses by Category</div></div>
        <div class="card-body" style="padding:14px;">
            <div style="position:relative;height:200px;"><canvas id="expChart"></canvas></div>
        </div>
    </div>
    @endif
</div>

@if($bankBalances->count())
<div class="card mb-6">
    <div class="card-head"><div class="card-title">Bank Balance Progression</div></div>
    <div class="card-body" style="padding:14px;">
        <div style="position:relative;height:160px;"><canvas id="bankChart"></canvas></div>
    </div>
</div>
@endif

{{-- Monthly breakdown + Top members (sortable) --}}
<div class="grid-2 mb-6">

    {{-- Monthly breakdown --}}
    <div class="card">
        <div class="card-head">
            <div class="card-title">Monthly Breakdown</div>
            <a href="{{ route('payments.index', ['year' => $fy->year]) }}" class="btn btn-outline btn-sm">All Payments</a>
        </div>
        <div class="card-body" style="padding:0;">
            <table id="monthly-tbl">
                <thead>
                    <tr>
                        <th class="sortable" data-col="0" data-type="number">
                            Month <span class="si"><span class="au"></span><span class="ad"></span></span>
                        </th>
                        <th class="sortable" data-col="1" data-type="number">
                            Collected (KES) <span class="si"><span class="au"></span><span class="ad"></span></span>
                        </th>
                        <th>Share</th>
                    </tr>
                </thead>
                <tbody>
                @php $yearTotal = array_sum($monthlyTotals); $months = \App\Models\Payment::MONTHS; @endphp
                @foreach($months as $num => $name)
                @php $amt = $monthlyTotals[$num] ?? 0; $pct = $yearTotal > 0 ? round($amt/$yearTotal*100,1) : 0; @endphp
                <tr>
                    <td data-val="{{ $num }}" style="font-weight:500">{{ $name }}</td>
                    <td data-val="{{ $amt }}" class="num {{ $amt > 0 ? 'pos' : 'dim' }}">{{ $amt > 0 ? number_format($amt) : '—' }}</td>
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

    {{-- Top members --}}
    <div class="card">
        <div class="card-head">
            <div class="card-title">Members — Investment</div>
            <a href="{{ route('members.index', ['year' => $fy->year]) }}" class="btn btn-outline btn-sm">All Members</a>
        </div>
        <div class="card-body" style="padding:0;">
            <table id="members-tbl">
                <thead>
                    <tr>
                        <th class="sortable" data-col="0" data-type="string">
                            Member <span class="si"><span class="au"></span><span class="ad"></span></span>
                        </th>
                        <th class="sortable" data-col="1" data-type="number">
                            Investment <span class="si"><span class="au"></span><span class="ad"></span></span>
                        </th>
                        <th class="sortable" data-col="2" data-type="number">
                            Pool % <span class="si"><span class="au"></span><span class="ad"></span></span>
                        </th>
                        <th class="sortable" data-col="3" data-type="string">
                            Status <span class="si"><span class="au"></span><span class="ad"></span></span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                @foreach($topMembers as $f)
                <tr>
                    <td data-val="{{ $f->member->name }}">
                        <a href="{{ route('members.show', $f->member) }}" style="font-weight:500;color:var(--forest);text-decoration:none;">
                            {{ $f->member->short_name }}
                        </a>
                    </td>
                    <td data-val="{{ $f->total_investment }}" class="num pos">{{ number_format($f->total_investment) }}</td>
                    <td data-val="{{ $f->pct_share }}" class="dim text-sm">{{ $f->pct_share > 0 ? number_format($f->pct_share * 100, 1).'%' : '—' }}</td>
                    <td data-val="{{ $f->welfare_owing >= 0 ? 'surplus' : 'deficit' }}">
                        <span class="badge {{ $f->welfare_owing >= 0 ? 'badge-g' : 'badge-r' }}">
                            {{ $f->welfare_owing >= 0 ? 'Surplus' : 'Deficit' }}
                        </span>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Deficit members (sortable) --}}
@if($deficitMembers->count())
<div class="card mb-6">
    <div class="card-head">
        <div class="card-title" style="color:var(--rust)">Members in Deficit</div>
        <span class="badge badge-r">{{ $deficitMembers->count() }} members</span>
    </div>
    <div class="card-body" style="padding:0;">
        <table id="deficit-tbl">
            <thead>
                <tr>
                    <th class="sortable" data-col="0" data-type="string">
                        Member <span class="si"><span class="au"></span><span class="ad"></span></span>
                    </th>
                    <th class="sortable" data-col="1" data-type="number">
                        Contributions C/F <span class="si"><span class="au"></span><span class="ad"></span></span>
                    </th>
                    <th class="sortable" data-col="2" data-type="number">
                        Welfare Received <span class="si"><span class="au"></span><span class="ad"></span></span>
                    </th>
                    <th class="sortable" data-col="3" data-type="number">
                        Deficit Amount <span class="si"><span class="au"></span><span class="ad"></span></span>
                    </th>
                </tr>
            </thead>
            <tbody>
            @foreach($deficitMembers as $f)
            <tr>
                <td data-val="{{ $f->member->name }}">
                    <a href="{{ route('members.show', $f->member) }}" style="font-weight:500;color:var(--forest);text-decoration:none;">
                        {{ $f->member->name }}
                    </a>
                </td>
                <td data-val="{{ $f->contributions_carried_forward }}" class="num">{{ number_format($f->contributions_carried_forward) }}</td>
                <td data-val="{{ $f->total_welfare }}" class="num">{{ number_format($f->total_welfare) }}</td>
                <td data-val="{{ abs($f->welfare_owing) }}" class="num neg">{{ number_format(abs($f->welfare_owing)) }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- All members full table (sortable) --}}
<div class="card">
    <div class="card-head">
        <div class="card-title">All Members — {{ $fy->year }}</div>
        <span class="badge badge-b">{{ $financials->count() }} members</span>
    </div>
    <div class="tbl-wrap">
        <table id="all-members-tbl">
            <thead>
                <tr>
                    <th class="sortable" data-col="0" data-type="string">
                        Member <span class="si"><span class="au"></span><span class="ad"></span></span>
                    </th>
                    <th class="sortable" data-col="1" data-type="number">
                        B/F <span class="si"><span class="au"></span><span class="ad"></span></span>
                    </th>
                    <th class="sortable" data-col="2" data-type="number">
                        C/F <span class="si"><span class="au"></span><span class="ad"></span></span>
                    </th>
                    <th class="sortable" data-col="3" data-type="number">
                        Welfare <span class="si"><span class="au"></span><span class="ad"></span></span>
                    </th>
                    <th class="sortable" data-col="4" data-type="number">
                        Investment <span class="si"><span class="au"></span><span class="ad"></span></span>
                    </th>
                    <th class="sortable" data-col="5" data-type="number">
                        Pool % <span class="si"><span class="au"></span><span class="ad"></span></span>
                    </th>
                    <th class="sortable" data-col="6" data-type="string">
                        Status <span class="si"><span class="au"></span><span class="ad"></span></span>
                    </th>
                </tr>
            </thead>
            <tbody>
            @foreach($financials as $f)
            <tr>
                <td data-val="{{ $f->member->name }}">
                    <a href="{{ route('members.show', $f->member) }}" style="font-weight:500;color:var(--forest);text-decoration:none;">
                        {{ $f->member->short_name }}
                    </a>
                </td>
                <td data-val="{{ $f->contributions_brought_forward }}" class="num dim">{{ number_format($f->contributions_brought_forward) }}</td>
                <td data-val="{{ $f->contributions_carried_forward }}" class="num">{{ number_format($f->contributions_carried_forward) }}</td>
                <td data-val="{{ $f->total_welfare }}" class="num">{{ number_format($f->total_welfare) }}</td>
                <td data-val="{{ $f->total_investment }}" class="num {{ $f->total_investment >= 0 ? 'pos' : 'neg' }}">{{ number_format($f->total_investment) }}</td>
                <td data-val="{{ $f->pct_share }}" class="dim text-sm">{{ $f->pct_share > 0 ? number_format($f->pct_share * 100, 1).'%' : '—' }}</td>
                <td data-val="{{ $f->welfare_owing >= 0 ? 'surplus' : 'deficit' }}">
                    <span class="badge {{ $f->welfare_owing >= 0 ? 'badge-g' : 'badge-r' }}">
                        {{ $f->welfare_owing >= 0 ? 'Surplus' : 'Deficit' }}
                    </span>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection

@push('scripts')
<script>
// ── Sortable tables ────────────────────────────────────────────────────────
function makeSortable(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    const tbody = table.querySelector('tbody');
    const ths   = table.querySelectorAll('th.sortable');
    let sortCol = -1, sortDir = 'asc';

    ths.forEach((th, idx) => {
        th.addEventListener('click', function () {
            sortDir = sortCol === idx ? (sortDir === 'asc' ? 'desc' : 'asc') : 'asc';
            sortCol = idx;
            ths.forEach(t => t.classList.remove('asc', 'desc'));
            this.classList.add(sortDir);

            const type = this.dataset.type || 'string';
            const rows = Array.from(tbody.querySelectorAll('tr'));

            rows.sort((a, b) => {
                let va = a.querySelectorAll('td')[idx]?.dataset.val ?? '';
                let vb = b.querySelectorAll('td')[idx]?.dataset.val ?? '';
                if (type === 'number') { va = parseFloat(va) || 0; vb = parseFloat(vb) || 0; }
                else { va = va.toLowerCase(); vb = vb.toLowerCase(); }
                return (va < vb ? -1 : va > vb ? 1 : 0) * (sortDir === 'asc' ? 1 : -1);
            });

            rows.forEach(r => tbody.appendChild(r));
        });
    });
}

['monthly-tbl', 'members-tbl', 'deficit-tbl', 'all-members-tbl'].forEach(makeSortable);

// ── Charts ─────────────────────────────────────────────────────────────────
const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const green = '#2d6a4f', mist = '#d8f3dc55', rust = '#c0392b';

const contribs = @json(array_values($monthlyTotals));
new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: { labels: MONTHS, datasets: [{ label:'KES', data:contribs, backgroundColor:mist, borderColor:green, borderWidth:1.5, borderRadius:4 }]},
    options: {
        responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{display:false}, tooltip:{callbacks:{label: c=>'KES '+Math.round(c.raw).toLocaleString()}}},
        scales:{
            x:{grid:{display:false},ticks:{font:{size:10}}},
            y:{grid:{color:'#f3f4f6'},ticks:{font:{size:10},callback: v=>v>=1000?(v/1000)+'k':v}}
        }
    }
});

@if(!empty($expensesByCat))
const expLabels = @json(array_keys($expensesByCat));
const expData   = @json(array_values($expensesByCat));
const catNames  = @json(\App\Models\ExpenseCategory::pluck('name','slug'));
const palette   = ['#1a3a2a','#2d6a4f','#52b788','#c0392b','#e67e22','#2980b9','#8e44ad','#16a085'];
new Chart(document.getElementById('expChart'), {
    type: 'doughnut',
    data: {
        labels: expLabels.map(k => catNames[k] || k.replace(/_/g,' ')),
        datasets: [{ data:expData, backgroundColor:palette, borderWidth:2, borderColor:'#fff' }]
    },
    options: {
        responsive:true, maintainAspectRatio:false,
        plugins:{
            legend:{position:'right',labels:{font:{size:10},boxWidth:10}},
            tooltip:{callbacks:{label: c=>c.label+': KES '+Math.round(c.raw).toLocaleString()}}
        }
    }
});
@endif

@if($bankBalances->count())
const bankData   = @json($bankBalances->pluck('closing_balance'));
const bankLabels = @json($bankBalances->map(fn($b) => \App\Models\Payment::MONTHS[$b->month] ?? 'Month '.$b->month));
new Chart(document.getElementById('bankChart'), {
    type: 'line',
    data:{ labels:bankLabels, datasets:[{ label:'Balance', data:bankData, borderColor:'#1a3a2a', backgroundColor:'#d8f3dc22', fill:true, tension:.4, pointRadius:4, pointBackgroundColor:'#2d6a4f' }]},
    options:{
        responsive:true, maintainAspectRatio:false,
        plugins:{legend:{display:false},tooltip:{callbacks:{label: c=>'KES '+Math.round(c.raw).toLocaleString()}}},
        scales:{
            x:{grid:{display:false},ticks:{font:{size:10}}},
            y:{grid:{color:'#f3f4f6'},ticks:{font:{size:10},callback: v=>v>=1000?(v/1000)+'k':v}}
        }
    }
});
@endif
</script>
@endpush
