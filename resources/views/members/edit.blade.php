@extends('layouts.app')
@section('title', 'Edit Member')

@section('topbar-actions')
<a href="{{ route('members.show', $member) }}" class="btn btn-outline btn-sm">← Back to Profile</a>
@endsection

@section('content')
<div style="max-width:560px;">
<div class="card">
    <div class="card-head">
        <div class="flex items-center gap-3">
            <div class="avatar">{{ $member->initials }}</div>
            <div class="card-title">{{ $member->name }}</div>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('members.update', $member) }}">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label class="form-label">Full Name <span style="color:var(--rust)">*</span></label>
                <input type="text" name="name" value="{{ old('name', $member->name) }}" class="form-control" required>
                @error('name')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" value="{{ old('phone', $member->phone) }}" class="form-control">
                    @error('phone')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Year Joined</label>
                    <input type="number" name="joined_year" value="{{ old('joined_year', $member->joined_year) }}" class="form-control" min="2000" max="2100">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control">{{ old('notes', $member->notes) }}</textarea>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $member->is_active) ? 'checked' : '' }}>
                    Active member
                </label>
            </div>
            <div class="flex gap-3 mt-4">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('members.show', $member) }}" class="btn btn-outline">Cancel</a>
                <form method="POST" action="{{ route('members.destroy', $member) }}" style="margin-left:auto"
                      onsubmit="return confirm('Deactivate {{ addslashes($member->name) }}? This can be undone.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">Deactivate</button>
                </form>
            </div>
        </form>
    </div>
</div>
</div>
@endsection
