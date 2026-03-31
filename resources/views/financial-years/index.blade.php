@extends('layouts.app')
@section('title', 'Financial Years')

@section('content')

@if($years->isEmpty())
    <div class="empty-state">
        <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <h3>No financial years yet</h3>
        <p>Import a spreadsheet to create your first financial year.</p>
        <a href="{{ route('import.show') }}" class="btn btn-primary mt-4">Import Spreadsheet</a>
    </div>
@else

@php
    $grandContrib  = $years->sum('total_contrib');
    $grandWelfare  = $years->sum('total_welfare');
    $grandInvest   = $years->sum('total_invest');
    $grandExpenses = $years->sum('total_expenses');
@endphp

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
    <div class="stat dark">
        <div class="stat-label">Years on Record</div>
        <div class="stat-value">{{ $years->count() }}</div>
        <div class="stat-sub">{{ $years->min('year') }}–{{ $years->max('year') }}</div>
    </div>
    <div class="stat">
        <div class="stat-label">All-time Contributions</div>
        <div class="stat-value" style="font-size:1.25rem">{{ number_format($grandContrib) }}</div>
        <div class="stat-sub">KES total</div>
    </div>
    <div class="stat green">
        <div class="stat-label">All-time Welfare</div>
        <div class="stat-value" style="font-size:1.25rem">{{ number_format($grandWelfare) }}</div>
        <div class="stat-sub">KES disbursed</div>
    </div>
    <div class="stat">
        <div class="stat-label">All-time Investment</div>
        <div class="stat-value" style="font-size:1.25rem">{{ number_format($grandInvest) }}</div>
        <div class="stat-sub">KES net pool</div>
    </div>
</div>

<div style="display:grid;gap:16px;">
@foreach($years as $fy)
<div class="card">
    <div class="card-head" style="flex-wrap:wrap;gap:10px;">
        <div class="flex items-center gap-3">
            <div style="width:46px;height:46px;border-radius:10px;background:var(--forest);color:var(--mist);display:flex;align-items:center;justify-content:center;font-family:'DM Serif Display',serif;font-size:1rem;flex-shrink:0;">
                {{ substr($fy->year, 2) }}
            </div>
            <div>
                <div class="card-title">Financial Year {{ $fy->year }}</div>
                <div class="text-sm text-mid" style="margin-top:2px;">
                    {{ $fy->member_count }} members recorded
                    @if($fy->welfare_per_member) &nbsp;·&nbsp; KES {{ number_format($fy->welfare_per_member) }} welfare/member @endif
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2" style="margin-left:auto;flex-wrap:wrap;">
            @if($fy->is_current)
                <span class="badge badge-g">Active Year</span>
            @endif
            <a href="{{ route('financial-years.show', $fy) }}" class="btn btn-outline btn-sm">View Details</a>
            <a href="{{ route('financial-years.edit', $fy) }}" class="btn btn-ghost btn-sm">Edit</a>
            <a href="{{ route('financial-years.export', $fy) }}"
               class="btn btn-ghost btn-sm"
               title="Export {{ $fy->year }} ledger to Excel"
               style="display:inline-flex;align-items:center;gap:5px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Export
            </a>
        </div>
    </div>

    <div class="card-body" style="padding:16px 20px;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;">
            @php
                $items = [
                    ['Contributions',  number_format($fy->total_contrib),  null],
                    ['Total Welfare',  number_format($fy->total_welfare),  null],
                    ['Net Investment', number_format($fy->total_invest),   $fy->total_invest >= 0 ? 'var(--leaf)' : 'var(--rust)'],
                    ['Expenses',       number_format($fy->total_expenses), 'var(--rust)'],
                    ['Expenditures',   number_format($fy->expenditures_total), 'var(--ink)'],
                    ['Exp. Entries',   number_format($fy->expenditures_count), null],
                    ['In Surplus',     $fy->surplus_count . ' members',    'var(--leaf)'],
                    ['In Deficit',     $fy->deficit_count . ' members',    $fy->deficit_count > 0 ? 'var(--rust)' : 'var(--mid)'],
                ];
            @endphp
            @foreach($items as [$label, $value, $color])
            <div style="background:var(--surface);border-radius:var(--r-sm);padding:10px 12px;">
                <div style="font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:var(--mid);margin-bottom:5px;">{{ $label }}</div>
                <div style="font-size:.95rem;font-weight:500;{{ $color ? "color:$color;" : '' }}">{{ $value }}</div>
            </div>
            @endforeach
        </div>
    </div>

    <div class="card-foot flex items-center justify-between">
        <span class="text-sm dim">{{ $fy->payment_count }} payment entries</span>
        <div class="flex gap-2">
            <a href="{{ route('payments.index', ['year' => $fy->year]) }}" class="btn btn-ghost btn-xs">Payments</a>
            <a href="{{ route('expenses.index', ['year' => $fy->year]) }}" class="btn btn-ghost btn-xs">Expenses</a>
            <a href="{{ route('members.index',  ['year' => $fy->year]) }}" class="btn btn-ghost btn-xs">Members</a>
            <a href="{{ route('expenditures.index',  ['year' => $fy->year]) }}" class="btn btn-ghost btn-xs">Expenditures</a>
        </div>
    </div>
</div>
@endforeach
</div>
@endif
@endsection
