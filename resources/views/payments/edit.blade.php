@extends('layouts.app')
@section('title', 'Edit Payment')

@section('topbar-actions')
<a href="{{ route('payments.index', ['year' => $payment->financialYear->year]) }}" class="btn btn-outline btn-sm">← Back</a>
@endsection

@section('content')
<div style="max-width:520px;">
<div class="card">
    <div class="card-head">
        <div class="card-title">Edit Payment</div>
        <span class="badge badge-b">{{ $payment->financialYear->year }} · {{ $payment->month_name }}</span>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('payments.update', $payment) }}">
            @csrf @method('PUT')
            <div class="form-group">
                <label class="form-label">Member</label>
                <select name="member_id" class="form-control" required>
                    @foreach($members as $m)
                    <option value="{{ $m->id }}" {{ $m->id==$payment->member_id ? 'selected':'' }}>{{ $m->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Financial Year</label>
                    <select name="financial_year_id" class="form-control" required>
                        @foreach($fyAll as $fy)
                        <option value="{{ $fy->id }}" {{ $fy->id==$payment->financial_year_id ? 'selected':'' }}>{{ $fy->year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Month</label>
                    <select name="month" class="form-control" required>
                        @foreach(\App\Models\Payment::MONTHS as $n => $mn)
                        <option value="{{ $n }}" {{ $n==$payment->month ? 'selected':'' }}>{{ $mn }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Amount (KES)</label>
                    <input type="number" name="amount" value="{{ old('amount', $payment->amount) }}" class="form-control" min="1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select name="payment_type" class="form-control">
                        @foreach(\App\Models\Payment::TYPES as $v => $l)
                        <option value="{{ $v }}" {{ $v==$payment->payment_type ? 'selected':'' }}>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Notes</label>
                <input type="text" name="notes" value="{{ old('notes', $payment->notes) }}" class="form-control" placeholder="Optional…">
            </div>
            <div class="flex gap-3 mt-4">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('payments.index', ['year'=>$payment->financialYear->year]) }}" class="btn btn-outline">Cancel</a>
                <form method="POST" action="{{ route('payments.destroy', $payment) }}" style="margin-left:auto"
                      onsubmit="return confirm('Delete this payment?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </div>
        </form>
    </div>
</div>
</div>
@endsection
