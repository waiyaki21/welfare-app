@extends('layouts.app')
@section('title', 'Edit Expense')

@section('topbar-actions')
<a href="{{ route('expenses.index', ['year' => $expense->financialYear->year]) }}" class="btn btn-outline btn-sm">← Back</a>
@endsection

@section('content')
<div style="max-width:520px;">
<div class="card">
    <div class="card-head">
        <div class="card-title">Edit Expense</div>
        @php
            $catModel = $categories->firstWhere('slug', $expense->category);
            $catColor = $catModel ? $catModel->color : '#fef3c7';
            $catName  = $catModel ? $catModel->name : $expense->category_name;
        @endphp
        <span class="badge" style="background:{{ $catColor }};color:#1a1a1a;">{{ $catName }}</span>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('expenses.update', $expense) }}">
            @csrf @method('PUT')
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Financial Year</label>
                    <select name="financial_year_id" class="form-control" required>
                        @foreach($fyAll as $fy)
                        <option value="{{ $fy->id }}" {{ $fy->id==$expense->financial_year_id ? 'selected':'' }}>{{ $fy->year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Month</label>
                    <select name="month" class="form-control" required>
                        @foreach(\App\Models\Payment::MONTHS as $n => $mn)
                        <option value="{{ $n }}" {{ $n==$expense->month ? 'selected':'' }}>{{ $mn }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-control" required>
                        @foreach($categories as $cat)
                        <option value="{{ $cat->slug }}" {{ $cat->slug==$expense->category ? 'selected':'' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Amount (KES)</label>
                    <input type="number" name="amount" value="{{ old('amount', $expense->amount) }}" class="form-control" min="0.01" step="0.01" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Notes</label>
                <input type="text" name="notes" value="{{ old('notes', $expense->notes) }}" class="form-control" placeholder="Optional…">
            </div>
            <div class="flex gap-3 mt-4">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('expenses.index', ['year'=>$expense->financialYear->year]) }}" class="btn btn-outline">Cancel</a>
                <form method="POST" action="{{ route('expenses.destroy', $expense) }}" style="margin-left:auto" onsubmit="return confirm('Delete this expense?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </div>
        </form>
    </div>
</div>
</div>
@endsection
