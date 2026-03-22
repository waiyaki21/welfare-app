@extends('layouts.app')
@section('title', 'Expense Categories')

@section('topbar-actions')
<a href="{{ route('expenses.index') }}" class="btn btn-ghost btn-sm">← Expenses</a>
@endsection

@section('content')
<div class="grid-2" style="align-items:start;">

    {{-- Left: existing categories --}}
    <div class="card">
        <div class="card-head">
            <div class="card-title">All Categories</div>
            <span class="badge badge-b">{{ $categories->count() }}</span>
        </div>
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Colour</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Expenses</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($categories as $cat)
                <tr id="row-{{ $cat->id }}">
                    <td>
                        <span style="display:inline-block;width:20px;height:20px;border-radius:4px;background:{{ $cat->color }};border:1px solid var(--border);vertical-align:middle;"></span>
                    </td>
                    <td style="font-weight:500">
                        <span class="badge" style="background:{{ $cat->color }};color:#1a1a1a;">{{ $cat->name }}</span>
                    </td>
                    <td class="dim text-sm" style="font-family:monospace">{{ $cat->slug }}</td>
                    <td class="num dim">{{ $cat->expenses_count }}</td>
                    <td>
                        @if($cat->is_active)
                            <span class="badge badge-g">Active</span>
                        @else
                            <span class="badge badge-mid">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <button onclick="openEdit({{ $cat->id }}, '{{ addslashes($cat->name) }}', '{{ $cat->color }}', {{ $cat->is_active ? 'true':'false' }})"
                                class="btn btn-ghost btn-xs">Edit</button>
                        @if($cat->expenses_count == 0)
                        <form method="POST" action="{{ route('expense-categories.destroy', $cat) }}"
                              style="display:inline"
                              onsubmit="return confirm('Delete category {{ addslashes($cat->name) }}?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-xs" style="color:var(--rust)">Del</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="dim text-sm" style="padding:30px;text-align:center">No categories yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Right: add + edit forms --}}
    <div style="display:flex;flex-direction:column;gap:16px;">

        {{-- Add new --}}
        <div class="card">
            <div class="card-head"><div class="card-title">Add Category</div></div>
            <div class="card-body">
                <form method="POST" action="{{ route('expense-categories.store') }}">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">Name <span style="color:var(--rust)">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Legal Retainer" required>
                        <div class="form-hint">A slug is generated automatically from the name</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Badge Colour</label>
                        <div class="flex items-center gap-3">
                            <input type="color" name="color" value="#fef3c7"
                                   style="width:44px;height:36px;padding:2px;border:1.5px solid var(--border);border-radius:var(--r-sm);cursor:pointer;background:none;">
                            <span class="text-sm dim">Choose a background colour for the badge</span>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-full" style="justify-content:center">Save Category</button>
                </form>
            </div>
        </div>

        {{-- Edit panel (shown when edit clicked) --}}
        <div class="card" id="edit-panel" style="display:none;">
            <div class="card-head">
                <div class="card-title">Edit Category</div>
                <button onclick="closeEdit()" class="btn btn-ghost btn-xs">✕</button>
            </div>
            <div class="card-body">
                <form method="POST" id="edit-form" action="">
                    @csrf @method('PUT')
                    <div class="form-group">
                        <label class="form-label">Name <span style="color:var(--rust)">*</span></label>
                        <input type="text" name="name" id="edit-name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Badge Colour</label>
                        <input type="color" name="color" id="edit-color"
                               style="width:44px;height:36px;padding:2px;border:1.5px solid var(--border);border-radius:var(--r-sm);cursor:pointer;background:none;">
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_active" id="edit-active" value="1" checked>
                            Active (visible in dropdowns)
                        </label>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <button type="button" onclick="closeEdit()" class="btn btn-outline">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Info box --}}
        <div style="padding:14px 16px;background:var(--surface);border-radius:var(--r-sm);border:1px solid var(--border);font-size:.855rem;color:var(--mid);line-height:1.65;">
            <strong style="color:var(--ink);display:block;margin-bottom:4px;">How categories work</strong>
            Categories are created automatically when you import a spreadsheet — any new expense label found in the sheet gets added here with a default colour. You can rename them and change their badge colour at any time. Deactivating a category hides it from dropdowns but keeps its historical expense records intact. A category with existing expense records cannot be deleted — deactivate it instead.
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const routes = {
    @foreach($categories as $cat)
    {{ $cat->id }}: '{{ route("expense-categories.update", $cat) }}',
    @endforeach
};

function openEdit(id, name, color, isActive) {
    document.getElementById('edit-panel').style.display = 'block';
    document.getElementById('edit-form').action = routes[id];
    document.getElementById('edit-name').value  = name;
    document.getElementById('edit-color').value = color;
    document.getElementById('edit-active').checked = isActive;
    document.getElementById('edit-panel').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function closeEdit() {
    document.getElementById('edit-panel').style.display = 'none';
}
</script>
@endpush
