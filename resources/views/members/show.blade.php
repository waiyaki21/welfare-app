@extends('layouts.app')
@section('title', $member->name)

@section('topbar-actions')
<a href="{{ route('members.edit', $member) }}" class="btn btn-outline btn-sm">Edit</a>
<a href="{{ route('members.index') }}" class="btn btn-ghost btn-sm">← Members</a>
@endsection

@push('styles')
<style>
.tab-bar-wrap{position:relative;background:var(--white);border-bottom:1px solid var(--border);border-radius:var(--r) var(--r) 0 0;}
.tab-bar{display:flex;overflow-x:auto;scroll-behavior:smooth;scrollbar-width:none;-ms-overflow-style:none;padding:0 20px;gap:2px;}
.tab-bar::-webkit-scrollbar{display:none;}
.tab-btn{flex-shrink:0;display:flex;align-items:center;gap:7px;padding:13px 18px 11px;font-size:.855rem;font-weight:500;color:var(--mid);background:none;border:none;border-bottom:2.5px solid transparent;cursor:pointer;white-space:nowrap;transition:color .13s,border-color .13s;margin-bottom:-1px;font-family:inherit;}
.tab-btn:hover{color:var(--forest);}
.tab-btn.active{color:var(--forest);border-bottom-color:var(--sage);}
.tab-badge{font-size:.68rem;font-weight:600;padding:2px 7px;border-radius:100px;background:var(--surface);color:var(--mid);border:1px solid var(--border);}
.tab-btn.active .tab-badge.surplus{background:var(--mist);color:#166534;border-color:#b7e4c7;}
.tab-btn.active .tab-badge.deficit{background:#fee2e2;color:#991b1b;border-color:#fca5a5;}
.tab-arrow{position:absolute;top:0;bottom:0;width:40px;display:flex;align-items:center;justify-content:center;border:none;cursor:pointer;color:var(--mid);font-size:1.1rem;z-index:10;opacity:0;pointer-events:none;transition:opacity .15s;background:var(--white);}
.tab-arrow.left{left:0;box-shadow:4px 0 8px rgba(255,255,255,.9);}
.tab-arrow.right{right:0;box-shadow:-4px 0 8px rgba(255,255,255,.9);}
.tab-arrow.visible{opacity:1;pointer-events:auto;}
.tab-panel{display:none;padding:22px 20px 24px;}
.tab-panel.active{display:block;}
.sum-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:22px;}
.sum-card{background:var(--surface);border-radius:var(--r-sm);padding:14px 16px;}
.sum-lbl{font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--mid);margin-bottom:5px;}
.sum-val{font-size:1.1rem;font-weight:500;color:var(--ink);line-height:1.1;}
.month-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:8px;margin-bottom:20px;}
.mcell{border-radius:8px;padding:10px 6px;border:1px solid var(--border);background:var(--surface);text-align:center;transition:transform .12s;}
.mcell:hover{transform:scale(1.04);}
.mcell.paid{background:var(--mist);border-color:#b7e4c7;}
.mcell-m{font-size:.66rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--soft);margin-bottom:4px;}
.mcell.paid .mcell-m{color:var(--leaf);}
.mcell-v{font-size:.84rem;font-weight:600;color:var(--soft);}
.mcell.paid .mcell-v{color:var(--forest);}
.section-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--mid);margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;}
.chart-wrap{position:relative;height:180px;margin-bottom:22px;}
</style>
@endpush

@section('content')

{{-- Profile header --}}
<div class="card mb-4">
    <div class="card-body">
        <div class="flex items-center gap-4">
            <div class="avatar avatar-lg">{{ $member->initials }}</div>
            <div style="flex:1">
                <div style="font-family:'DM Serif Display',serif;font-size:1.3rem;color:var(--forest)">{{ $member->name }}</div>
                <div class="dim text-sm" style="margin-top:3px">
                    {{ $member->phone ?? 'No phone on record' }}
                    @if($member->joined_year) &nbsp;·&nbsp; Joined {{ $member->joined_year }} @endif
                    @if(!$member->is_active) &nbsp;·&nbsp; <span style="color:var(--mid)">Inactive</span> @endif
                </div>
                @if($member->notes)
                    <div class="text-sm" style="margin-top:6px;color:var(--mid)">{{ $member->notes }}</div>
                @endif
            </div>
            <button onclick="openPaymentModal()" class="btn btn-primary btn-sm">+ Record Payment</button>
        </div>
    </div>
</div>

@if($financials->isEmpty())
<div class="card">
    <div class="card-body">
        <div class="empty-state" style="padding:40px">
            <p>No financial records yet for this member.</p>
            <button onclick="openPaymentModal()" style="color:var(--leaf);background:none;border:none;cursor:pointer;font-weight:500;margin-top:8px">
                Record the first payment →
            </button>
        </div>
    </div>
</div>

@else
@php
    $tabYears  = $financials->sortKeysDesc()->keys()->toArray();
    $firstYear = $tabYears[0] ?? null;
@endphp

<div class="card" style="padding:0;overflow:visible;">

    {{-- Tab bar --}}
    <div class="tab-bar-wrap">
        <button class="tab-arrow left"  id="arr-l" onclick="scrollTabs(-160)">‹</button>
        <div class="tab-bar" id="tab-bar">
            @foreach($tabYears as $yr)
            @php
                $fin     = $financials[$yr];
                $surplus = $fin->welfare_owing >= 0;
            @endphp
            <button class="tab-btn {{ $yr === $firstYear ? 'active' : '' }}"
                    data-panel="panel-{{ $yr }}"
                    onclick="switchTab('panel-{{ $yr }}', this)">
                {{ $yr }}
                <span class="tab-badge {{ $surplus ? 'surplus' : 'deficit' }}">
                    {{ $surplus ? 'Surplus' : 'Deficit' }}
                </span>
            </button>
            @endforeach
        </div>
        <button class="tab-arrow right" id="arr-r" onclick="scrollTabs(160)">›</button>
    </div>

    {{-- Panels --}}
    @foreach($tabYears as $yr)
    @php
        $fin          = $financials[$yr];
        $pymts        = $paymentsByYear->get($yr, collect());
        $welfare      = $welfareEvents->filter(fn($w) => $w->financialYear->year == $yr);
        $months       = \App\Models\Payment::MONTHS;
        $byMonth      = $pymts->groupBy('month');
        $yearContrib  = (float) $pymts->sum('amount');
        $surplus      = $fin->welfare_owing >= 0;
    @endphp

    <div class="tab-panel {{ $yr === $firstYear ? 'active' : '' }}" id="panel-{{ $yr }}">

        {{-- Summary --}}
        <div class="sum-grid">
            <div class="sum-card">
                <div class="sum-lbl">Contributions B/F</div>
                <div class="sum-val">{{ number_format($fin->contributions_brought_forward) }}</div>
            </div>
            <div class="sum-card">
                <div class="sum-lbl">{{ $yr }} Contributions</div>
                <div class="sum-val pos">{{ number_format($yearContrib) }}</div>
            </div>
            <div class="sum-card">
                <div class="sum-lbl">Contributions C/F</div>
                <div class="sum-val">{{ number_format($fin->contributions_carried_forward) }}</div>
            </div>
            <div class="sum-card">
                <div class="sum-lbl">Total Welfare</div>
                <div class="sum-val">{{ number_format($fin->total_welfare) }}</div>
            </div>
            <div class="sum-card">
                <div class="sum-lbl">Net Investment</div>
                <div class="sum-val {{ $fin->total_investment >= 0 ? 'pos' : 'neg' }}">
                    {{ number_format($fin->total_investment) }}
                </div>
            </div>
            <div class="sum-card">
                <div class="sum-lbl">Pool Share</div>
                <div class="sum-val">{{ $fin->pct_share_formatted }}</div>
            </div>
            <div class="sum-card">
                <div class="sum-lbl">Welfare Owing</div>
                <div class="sum-val {{ $fin->welfare_owing < 0 ? 'neg' : '' }}">
                    {{ $fin->welfare_owing != 0 ? number_format(abs($fin->welfare_owing)) : '—' }}
                </div>
            </div>
            <div class="sum-card" style="display:flex;align-items:center;justify-content:center;">
                <span class="badge {{ $surplus ? 'badge-g' : 'badge-r' }}" style="font-size:.85rem;padding:6px 16px;">
                    {{ $surplus ? 'Surplus' : 'Deficit' }}
                </span>
            </div>
        </div>

        {{-- Monthly heat grid --}}
        <div class="section-label">Monthly payments</div>
        <div class="month-grid">
            @foreach($months as $num => $mname)
            @php
                $mPays = $byMonth->get($num, collect());
                $paid  = $mPays->isNotEmpty();
            @endphp
            <div class="mcell {{ $paid ? 'paid' : '' }}">
                <div class="mcell-m">{{ substr($mname, 0, 3) }}</div>
                <div class="mcell-v">{{ $paid ? number_format($mPays->sum('amount')) : '—' }}</div>
            </div>
            @endforeach
        </div>

        {{-- Bar chart --}}
        @if($pymts->isNotEmpty())
        <div class="chart-wrap">
            <canvas id="chart-{{ $yr }}"></canvas>
        </div>
        @endif

        {{-- Payment records --}}
        <div class="section-label">
            <span>Payment records</span>
            @if($pymts->isNotEmpty())
            <span style="color:var(--leaf);text-transform:none;letter-spacing:0;font-weight:400;font-size:.8rem;">
                {{ $pymts->count() }} entries &nbsp;·&nbsp; KES {{ number_format($pymts->sum('amount')) }}
            </span>
            @endif
        </div>

        @if($pymts->isEmpty())
        <div style="padding:24px;text-align:center;color:var(--mid);background:var(--surface);border-radius:var(--r-sm);margin-bottom:22px;font-size:.875rem;">
            No payments recorded for {{ $yr }}.
            <button onclick="openPaymentModal()" style="color:var(--leaf);background:none;border:none;cursor:pointer;font-weight:500;margin-left:4px;">Add one →</button>
        </div>
        @else
        <div class="tbl-wrap" style="margin-bottom:24px;">
            <table>
                <thead><tr><th>Month</th><th>Amount (KES)</th><th>Type</th><th>Notes</th><th></th></tr></thead>
                <tbody>
                @foreach($pymts->sortBy('month') as $pay)
                <tr>
                    <td style="font-weight:500">{{ $pay->month_name }}</td>
                    <td class="num pos">{{ number_format($pay->amount) }}</td>
                    <td><span class="badge badge-mid">{{ $pay->type_name }}</span></td>
                    <td class="dim text-sm">{{ $pay->notes ?? '—' }}</td>
                    <td>
                        <div class="flex gap-1">
                            <a href="{{ route('payments.edit', $pay) }}" class="btn btn-ghost btn-xs">Edit</a>
                            <form method="POST" action="{{ route('payments.destroy', $pay) }}"
                                  onsubmit="return confirm('Delete this payment?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-xs" style="color:var(--rust)">Del</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- Welfare events --}}
        <div class="section-label">Welfare events</div>
        @if($welfare->isEmpty())
        <div style="padding:20px 24px;color:var(--mid);background:var(--surface);border-radius:var(--r-sm);font-size:.875rem;">
            No welfare events recorded for {{ $yr }}.
        </div>
        @else
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Date</th><th>Reason</th><th>Amount (KES)</th><th>Notes</th></tr></thead>
                <tbody>
                @foreach($welfare->sortByDesc('event_date') as $we)
                <tr>
                    <td class="dim text-sm">{{ $we->event_date?->format('d M Y') ?? '—' }}</td>
                    <td><span class="badge badge-a">{{ $we->reason_name }}</span></td>
                    <td class="num">{{ number_format($we->amount) }}</td>
                    <td class="dim text-sm">{{ $we->notes ?? '—' }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

    </div>{{-- end panel --}}
    @endforeach

</div>{{-- end card --}}
@endif

{{-- Quick Payment Modal --}}
<div class="modal-backdrop" id="paymentModal">
    <div class="modal">
        <div class="modal-head">
            <div class="modal-title">Record Payment — {{ $member->name }}</div>
            <button class="close-btn" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body">
            <div id="modal-msg" class="alert" style="display:none"></div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Year</label>
                    <select id="m-year" class="form-control">
                        @foreach($years as $yr)
                        <option value="{{ \App\Models\FinancialYear::where('year',$yr)->first()?->id }}">{{ $yr }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Month</label>
                    <select id="m-month" class="form-control">
                        @foreach(\App\Models\Payment::MONTHS as $n => $mn)
                        <option value="{{ $n }}" {{ $n==date('n') ? 'selected':'' }}>{{ $mn }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Amount (KES)</label>
                    <input type="number" id="m-amount" class="form-control" placeholder="e.g. 2500" min="1">
                </div>
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select id="m-type" class="form-control">
                        <option value="contribution">Contribution</option>
                        <option value="arrears">Arrears</option>
                        <option value="lump_sum">Lump Sum</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Notes (optional)</label>
                <input type="text" id="m-notes" class="form-control" placeholder="Any remarks…">
            </div>
        </div>
        <div class="modal-foot">
            <button onclick="closeModal()" class="btn btn-outline">Cancel</button>
            <button onclick="submitPayment()" id="m-btn" class="btn btn-primary">Save Payment</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ── Chart data from server ───────────────────────────────────────
const chartData = {
@foreach($tabYears as $yr)
@php
    $pymts   = $paymentsByYear->get($yr, collect());
    $byMonth = $pymts->groupBy('month');
    $vals    = [];
    for ($m = 1; $m <= 12; $m++) {
        $vals[] = (float) $byMonth->get($m, collect())->sum('amount');
    }
@endphp
    '{{ $yr }}': @json($vals),
@endforeach
};
const MN = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const drawn = {};

function drawChart(yr) {
    if (drawn[yr]) return;
    const canvas = document.getElementById('chart-' + yr);
    if (!canvas || !window.Chart) return;
    drawn[yr] = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: MN,
            datasets: [{
                label: 'KES',
                data: chartData[yr] || [],
                backgroundColor: '#d8f3dc99',
                borderColor: '#2d6a4f',
                borderWidth: 1.5,
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: c => c.raw > 0 ? 'KES ' + Math.round(c.raw).toLocaleString() : 'No payment' } }
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 10 } } },
                y: { grid: { color: '#f3f4f6' }, ticks: { font: { size: 10 }, callback: v => v >= 1000 ? (v/1000)+'k' : v } }
            }
        }
    });
}

// ── Tab switching ────────────────────────────────────────────────
function switchTab(panelId, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(panelId).classList.add('active');
    btn.classList.add('active');
    btn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    drawChart(panelId.replace('panel-', ''));
}

// ── Scroll arrows ────────────────────────────────────────────────
const bar  = document.getElementById('tab-bar');
const arrL = document.getElementById('arr-l');
const arrR = document.getElementById('arr-r');

function updateArrows() {
    if (!bar) return;
    const canLeft  = bar.scrollLeft > 8;
    const canRight = bar.scrollLeft < bar.scrollWidth - bar.clientWidth - 8;
    arrL?.classList.toggle('visible', canLeft);
    arrR?.classList.toggle('visible', canRight);
}
function scrollTabs(d) { bar?.scrollBy({ left: d, behavior: 'smooth' }); }

if (bar) {
    bar.addEventListener('scroll', updateArrows, { passive: true });
    window.addEventListener('resize', updateArrows);
    setTimeout(updateArrows, 120);
}

// Draw chart for first active tab
document.addEventListener('DOMContentLoaded', () => {
    const first = '{{ $firstYear }}';
    if (first) drawChart(first);
});

// ── Payment modal ────────────────────────────────────────────────
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

function openPaymentModal() {
    document.getElementById('paymentModal').classList.add('open');
    setTimeout(() => document.getElementById('m-amount')?.focus(), 50);
}
function closeModal() { document.getElementById('paymentModal').classList.remove('open'); }
document.getElementById('paymentModal').addEventListener('click', e => { if (e.target.id === 'paymentModal') closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

async function submitPayment() {
    const msg = document.getElementById('modal-msg');
    const btn = document.getElementById('m-btn');
    const amt = document.getElementById('m-amount').value;
    if (!amt || amt < 1) { showMsg('error', 'Enter a valid amount.'); return; }
    btn.disabled = true; btn.textContent = 'Saving…';
    try {
        const r = await fetch('{{ route("payments.quick") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({
                member_id:         {{ $member->id }},
                financial_year_id: document.getElementById('m-year').value,
                month:             document.getElementById('m-month').value,
                amount:            amt,
                payment_type:      document.getElementById('m-type').value,
                notes:             document.getElementById('m-notes').value,
            })
        });
        const d = await r.json();
        if (d.success) {
            showMsg('success', `KES ${parseInt(amt).toLocaleString()} recorded for ${d.month_name}.`);
            document.getElementById('m-amount').value = '';
            document.getElementById('m-notes').value  = '';
            setTimeout(() => location.reload(), 1200);
        } else { showMsg('error', 'Save failed — check your inputs.'); }
    } catch (e) { showMsg('error', 'Network error — please try again.'); }
    finally { btn.disabled = false; btn.textContent = 'Save Payment'; }
}

function showMsg(type, text) {
    const el = document.getElementById('modal-msg');
    el.className = type === 'success' ? 'alert alert-success' : 'alert alert-error';
    el.textContent = text;
    el.style.display = 'block';
}
</script>
@endpush
