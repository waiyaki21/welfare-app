@extends('layouts.app')
@section('title', 'Edit Year ' . $fy->year)

@section('topbar-actions')
<a href="{{ route('financial-years.show', $fy) }}" class="btn btn-outline btn-sm">← Back to {{ $fy->year }}</a>
@endsection

@section('content')
<div style="max-width:520px;">
<div class="card">
    <div class="card-head"><div class="card-title">Edit Financial Year {{ $fy->year }}</div></div>
    <div class="card-body">
        <form method="POST" action="{{ route('financial-years.update', $fy) }}">
            @csrf @method('PUT')

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Year <span style="color:var(--rust)">*</span></label>
                    <input type="number" name="year" value="{{ old('year', $fy->year) }}"
                           class="form-control" min="2000" max="2100" required>
                    @error('year')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Welfare per Member (KES)</label>
                    <input type="number" name="welfare_per_member"
                           value="{{ old('welfare_per_member', $fy->welfare_per_member) }}"
                           class="form-control" min="0" step="100">
                    <div class="form-hint">Standard welfare amount paid to each member this year</div>
                    @error('welfare_per_member')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Sheet Name</label>
                <input type="text" name="sheet_name"
                       value="{{ old('sheet_name', $fy->sheet_name) }}"
                       class="form-control" placeholder="e.g. YEAR 2022">
                <div class="form-hint">The original spreadsheet tab name — for reference only</div>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_current" value="1"
                           {{ old('is_current', $fy->is_current) ? 'checked' : '' }}>
                    <div>
                        <div style="font-weight:500">Set as active year</div>
                        <div class="text-sm text-mid">This year will be shown as the default across the app</div>
                    </div>
                </label>
            </div>

            <div style="padding:14px;background:#fef3c7;border-radius:var(--r-sm);border-left:3px solid var(--amber);margin-bottom:20px;" class="text-sm">
                <strong>Note:</strong> Editing this record only changes metadata (year label, welfare amount, active flag).
                To change financial data, use the Payments and Expenses pages, or re-import the spreadsheet.
            </div>

            <div class="flex gap-3">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('financial-years.show', $fy) }}" class="btn btn-outline">Cancel</a>
                <form method="POST" action="{{ route('financial-years.destroy', $fy) }}"
                      style="margin-left:auto;"
                      onsubmit="return confirm('Delete financial year {{ $fy->year }} and ALL its data permanently? This cannot be undone.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">Delete Year</button>
                </form>
            </div>
        </form>
    </div>
</div>
</div>
@endsection
