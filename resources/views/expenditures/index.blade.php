@extends('layouts.app')
@section('title', 'Expenditures')

@section('topbar-actions')
<a href="{{ route('expenditures.create', ['year' => $selectedYear]) }}" class="btn btn-primary btn-sm">Add Expenditure</a>
@endsection

@section('content')
<div class="card mb-6">
    <div class="card-head">
        <div class="card-title">Expenditure Summary</div>
        <form method="GET" class="flex items-center gap-2">
            <label class="text-sm text-mid">Year</label>
            <select name="year" class="form-control" style="width:auto;padding:7px 28px 7px 10px;" onchange="this.form.submit()">
                @foreach($years as $yr)
                    <option value="{{ $yr }}" {{ (int) $yr === (int) $selectedYear ? 'selected' : '' }}>{{ $yr }}</option>
                @endforeach
            </select>
        </form>
    </div>
    <div class="card-body">
        <div class="stats-grid" style="margin-bottom:0;">
            <div class="stat">
                <div class="stat-label">Total Expenditures</div>
                <div class="stat-value">{{ number_format($expenditures->count()) }}</div>
                <div class="stat-sub">Entries in {{ $selectedYear }}</div>
            </div>
            <div class="stat green">
                <div class="stat-label">Year Total (KES)</div>
                <div class="stat-value">{{ number_format($yearTotal, 2) }}</div>
                <div class="stat-sub">Grouped by month below</div>
            </div>
        </div>
    </div>
</div>

@if($monthSummaries->isEmpty())
<div class="card">
    <div class="card-body">
        <div class="empty-state" style="padding:40px;">
            <p>No expenditures found for this year.</p>
        </div>
    </div>
</div>
@else
<div class="grid-2">
    @foreach($monthSummaries as $monthSummary)
        <div class="card">
            <div class="card-head">
                <div class="card-title">{{ $monthSummary['month_name'] }}</div>
                <span class="badge badge-b">{{ $monthSummary['count'] }} {{ \Illuminate\Support\Str::plural('entry', $monthSummary['count']) }}</span>
            </div>
            <div class="card-body" style="padding:0;">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th class="num">Amount (KES)</th>
                            <th style="width:150px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($monthSummary['items'] as $item)
                            <tr>
                                <td class="truncate" title="{{ $item->name }}">{{ $item->name }}</td>
                                <td class="num">{{ number_format($item->amount, 2) }}</td>
                                <td>
                                    <div class="flex items-center gap-1">
                                        <a href="{{ route('expenditures.show', $item) }}" class="btn btn-ghost btn-xs">View</a>
                                        <a href="{{ route('expenditures.edit', $item) }}" class="btn btn-ghost btn-xs">Edit</a>
                                        <form method="POST" action="{{ route('expenditures.destroy', $item) }}" onsubmit="return confirm('Delete this expenditure?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-xs" style="color:var(--rust)">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-foot flex justify-between items-center">
                <span class="text-sm text-mid">Month Total</span>
                <span class="num text-forest" style="font-weight:700;">KES {{ number_format($monthSummary['total'], 2) }}</span>
            </div>
        </div>
    @endforeach
</div>
@endif
@endsection
