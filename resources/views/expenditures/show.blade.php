@extends('layouts.app')
@section('title', 'Expenditure Details')

@section('topbar-actions')
<div class="flex items-center gap-2">
    <a href="{{ route('expenditures.index', ['year' => $expenditure->financialYear->year]) }}" class="btn btn-outline btn-sm">Back</a>
    <a href="{{ route('expenditures.edit', $expenditure) }}" class="btn btn-primary btn-sm">Edit</a>
</div>
@endsection

@section('content')
<div class="card mb-6">
    <div class="card-head">
        <div class="card-title">{{ $expenditure->narration ?? 'Unspecified' }}</div>
        <span class="badge badge-mid">FY {{ $expenditure->financialYear->year }}</span>
    </div>
    <div class="card-body">
        <div class="stats-grid" style="margin-bottom:0;">
            <div class="stat">
                <div class="stat-label">Entries</div>
                <div class="stat-value">{{ number_format($groupRows->count()) }}</div>
                <div class="stat-sub">Narration group</div>
            </div>
            <div class="stat green">
                <div class="stat-label">Total Amount (KES)</div>
                <div class="stat-value">{{ number_format($total, 2) }}</div>
                <div class="stat-sub">{{ $expenditure->narration ?? 'Unspecified' }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <div class="card-title">Expenditures in Narration</div>
    </div>
    <div class="card-body" style="padding:0;">
        <table>
            <thead>
                <tr>
                    <th>Expense</th>
                    <th class="num">Amount (KES)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($groupRows as $row)
                    <tr>
                        <td>{{ $row->name }}</td>
                        <td class="num">{{ number_format($row->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="text-mid">No expenditures found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-foot flex justify-between items-center">
        <span class="text-sm text-mid">Total</span>
        <span class="num text-forest" style="font-weight:700;">KES {{ number_format($total, 2) }}</span>
    </div>
</div>

<div class="mt-4">
    <form method="POST" action="{{ route('expenditures.destroy', $expenditure) }}" onsubmit="return confirm('Delete this expenditure?')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-danger btn-sm">Delete Expenditure</button>
    </form>
</div>
@endsection
