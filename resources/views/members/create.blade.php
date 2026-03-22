@extends('layouts.app')
@section('title', 'Add Member')

@section('topbar-actions')
<a href="{{ route('members.index') }}" class="btn btn-outline btn-sm">← Back</a>
@endsection

@section('content')
<div style="max-width:560px;">
<div class="card">
    <div class="card-head"><div class="card-title">New Member</div></div>
    <div class="card-body">
        <form method="POST" action="{{ route('members.store') }}">
            @csrf
            <div class="form-group">
                <label class="form-label">Full Name <span style="color:var(--rust)">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" class="form-control" placeholder="e.g. Christine Wanjiku Tonui" autofocus required>
                @error('name')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" class="form-control" placeholder="e.g. 0722475225">
                    @error('phone')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Year Joined</label>
                    <input type="number" name="joined_year" value="{{ old('joined_year', date('Y')) }}" class="form-control" min="2000" max="2100">
                    @error('joined_year')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" placeholder="Optional notes about this member…">{{ old('notes') }}</textarea>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                    Active member
                </label>
            </div>
            <div class="flex gap-3 mt-4">
                <button type="submit" class="btn btn-primary">Create Member</button>
                <a href="{{ route('members.index') }}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
@endsection
