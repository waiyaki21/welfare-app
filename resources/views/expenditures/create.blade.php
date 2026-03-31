@extends('layouts.app')
@section('title', 'Add Expenditure')

@section('topbar-actions')
<a href="{{ route('expenditures.index', ['year' => $selectedYear]) }}" class="btn btn-outline btn-sm">Back</a>
@endsection

@section('content')
<div style="max-width:560px;">
<div class="card">
    <div class="card-head"><div class="card-title">New Expenditure</div></div>
    <div class="card-body">
        <form method="POST" action="{{ route('expenditures.store') }}">
            @csrf
            <div class="form-group">
                <label class="form-label">Financial Year <span style="color:var(--rust)">*</span></label>
                <select name="financial_year_id" class="form-control" required>
                    @foreach($years as $fy)
                    <option value="{{ $fy->id }}" {{ (int) $selectedYear === (int) $fy->year ? 'selected' : '' }}>{{ $fy->year }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Name <span style="color:var(--rust)">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
            </div>
            <div class="form-group">
                <label class="form-label">Narration (Optional)</label>
                <input type="text" name="narration" class="form-control" value="{{ old('narration') }}" placeholder="e.g. General, Operations">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Amount (KES) <span style="color:var(--rust)">*</span></label>
                    <input type="number" name="amount" class="form-control" min="0.01" step="0.01" value="{{ old('amount') }}" required>
                </div>
            </div>
            <div class="flex gap-3 mt-4">
                <button type="submit" class="btn btn-primary">Save Expenditure</button>
                <a href="{{ route('expenditures.index', ['year' => $selectedYear]) }}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
@endsection
