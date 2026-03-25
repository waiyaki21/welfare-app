@extends('layouts.app')
@section('title', 'Edit Expenditure')

@section('topbar-actions')
<a href="{{ route('expenditures.index', ['year' => $expenditure->financialYear->year]) }}" class="btn btn-outline btn-sm">Back</a>
@endsection

@section('content')
<div style="max-width:560px;">
<div class="card">
    <div class="card-head"><div class="card-title">Edit Expenditure</div></div>
    <div class="card-body">
        <form method="POST" action="{{ route('expenditures.update', $expenditure) }}">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label class="form-label">Financial Year <span style="color:var(--rust)">*</span></label>
                <select name="financial_year_id" class="form-control" required>
                    @foreach($years as $fy)
                    <option value="{{ $fy->id }}" {{ (int) old('financial_year_id', $expenditure->financial_year_id) === (int) $fy->id ? 'selected' : '' }}>{{ $fy->year }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Name <span style="color:var(--rust)">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $expenditure->name) }}" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Amount (KES) <span style="color:var(--rust)">*</span></label>
                    <input type="number" name="amount" class="form-control" min="0.01" step="0.01" value="{{ old('amount', $expenditure->amount) }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Month</label>
                    <select name="month" class="form-control">
                        <option value="">-</option>
                        @foreach(\App\Models\Payment::MONTHS as $n => $mn)
                        <option value="{{ $n }}" {{ (string) old('month', $expenditure->month) === (string) $n ? 'selected' : '' }}>{{ $mn }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex gap-3 mt-4">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('expenditures.index', ['year' => $expenditure->financialYear->year]) }}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
@endsection

