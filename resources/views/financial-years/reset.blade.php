@extends('layouts.app')
@section('title', 'Reset Database')

@push('styles')
<style>
.reset-grid {
    display: grid;
    grid-template-columns: 60% 40%;
    gap: 20px;
    max-width: 100%;
    margin: 0 auto;
    padding: 0 20px;
}

/* Responsive */
@media (max-width: 900px) {
    .reset-grid {
        grid-template-columns: 1fr;
    }
}
</style>
@endpush

@section('content')
<div id="reset-page">
    {{-- Banner  --}}
    <div id="reset-banner"
        style="width:100%;background:#fee2e2;border-bottom:1.5px solid #ffe9e9;padding:22px 0;margin-bottom:24px;border-radius:var(--r)">

        <div style="max-width:1100px;margin:0 auto;padding:0 20px;">
            <div class="flex items-center gap-3" style="margin-bottom:12px;">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#991b1b" stroke-width="2" style="flex-shrink:0;">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>

                <div id="banner-title"
                    style="font-family:'DM Serif Display',serif;font-size:1.25rem;color:#991b1b;">
                    This will permanently delete all financial data
                </div>
            </div>

            <p id="banner-text"
            style="font-size:.9rem;color:#7f1d1d;line-height:1.7;">
                Resetting removes all members, payments, expenses, and financial records.
            </p>
        </div>
    </div>

    <div class="reset-grid">
        {{-- What will be deleted --}}
        <div class="card">
            <div class="card-head"><div class="card-title">What will be deleted</div></div>
            <div class="card-body" style="padding:0;">
                <table>
                    <thead><tr><th>Table</th><th>Records</th></tr></thead>
                    <tbody>
                        <tr><td>Members</td>                   <td class="num">{{ number_format($counts['members']) }}</td></tr>
                        <tr><td>Financial Years</td>           <td class="num">{{ number_format($counts['financial_years']) }}</td></tr>
                        <tr><td>Payment Records</td>           <td class="num">{{ number_format($counts['payments']) }}</td></tr>
                        <tr><td>Expense Records</td>           <td class="num">{{ number_format($counts['expenses']) }}</td></tr>
                        <tr><td>Expenditure Records</td>       <td class="num">{{ number_format($counts['expenditures']) }}</td></tr>
                        <tr><td>Member Financial Summaries</td><td class="num">{{ number_format($counts['member_financials']) }}</td></tr>
                        <tr><td>Default Settings</td>          <td class="num">{{ number_format($counts['settings']) }}</td></tr>
                        <tr style="color:var(--rust)">
                            <td>User Accounts <span class="badge badge-r" style="margin-left:6px;font-size:.65rem">optional</span></td>
                            <td class="num">{{ number_format($counts['users']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Confirmation form --}}
        <div class="card">
            <div class="card-head"><div class="card-title">Confirm Reset</div></div>
            <div class="card-body">
                <form method="POST" action="{{ route('db.reset.execute') }}">
                    @csrf

                    {{-- Optional: also reset users --}}
                    <div class="form-group" style="padding:14px 16px;background:#fff8f8;border-radius:var(--r-sm);border:1.5px solid #ffe9e9;margin-bottom:18px;">
                        <label class="checkbox-label">
                            <input type="checkbox" name="reset_users" value="1" id="reset-users-chk"
                                onchange="updateWarning(this.checked)"
                                style="width:17px;height:17px;accent-color:var(--rust);">
                            <div>
                                <div style="font-weight:500;color:var(--rust)">Also delete user accounts &amp; app settings</div>
                                <div class="text-sm text-mid" style="margin-top:2px;">
                                    You will be logged out and redirected to the login page. Requires re-registering.
                                </div>
                            </div>
                        </label>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Type <strong id="confirm-word">RESET</strong> to confirm
                        </label>
                        <input 
                            type="text"
                            name="confirm_text"
                            id="confirm-input"
                            class="form-control"
                            placeholder="Type RESET here…"
                            autocomplete="off"
                            oninput="checkConfirm()"
                            onkeypress="return /^[a-zA-Z\s]$/.test(event.key)"
                        >
                        @error('confirm_text')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div id="action-row" class="flex gap-3" style="display:none;">
                        <button type="submit" id="reset-btn" disabled
                                class="btn btn-danger"
                                onclick="return confirm('Final confirmation — this cannot be undone.')">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                <path d="M10 11v6"/><path d="M14 11v6"/>
                            </svg>
                            <span id="reset-btn-label">Reset Association Data</span>
                        </button>
                        <a href="{{ route('financial-years.index') }}" class="btn btn-outline">Cancel — Go Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function checkConfirm() {
    const input = document.getElementById('confirm-input');

    // Force uppercase + clean input
    input.value = input.value
        .toUpperCase()
        .replace(/[^A-Z\s]/g, '')
        .replace(/\s+/g, ' ');

    const val  = input.value.trim();
    const word = document.getElementById('reset-users-chk').checked ? 'RESET ALL' : 'RESET';
    document.getElementById('reset-btn').disabled = (val !== word);

    const actionRow = document.getElementById('action-row');

    // 🔥 Show or hide buttons
    if (val === word) {
        actionRow.style.display = 'flex';
    } else {
        actionRow.style.display = 'none';
    }
}
function updateWarning(checked) {
    const word    = checked ? 'RESET ALL' : 'RESET';
    const btnLbl  = checked ? 'Reset Everything' : 'Reset Association Data';

    document.getElementById('confirm-word').textContent = word;
    document.getElementById('reset-btn-label').textContent = btnLbl;

    const input = document.getElementById('confirm-input');
    input.value = '';
    input.placeholder = `Type ${word} here\u2026`;

    // 🔥 Update banner dynamically
    const bannerTitle = document.getElementById('banner-title');
    const bannerText  = document.getElementById('banner-text');
    const bannerColor = document.getElementById('reset-banner');

    if (checked) {
        bannerTitle.textContent = 'This will permanently clear the database';
        bannerText.textContent =
            'All financial data, user accounts, and application settings will be permanently removed. You will be logged out and must set up the system again.';
        bannerColor.style.background = '#ffe9e9';
    } else {
        bannerTitle.textContent = 'This will permanently delete all financial data';
        bannerText.textContent =
            'Resetting removes all members, payments, expenses, and financial records. User accounts and settings will remain intact.';
        bannerColor.style.background = '#fee2e2';
    }

    // hide buttons again
    document.getElementById('action-row').style.display = 'none';
}
</script>
@endpush
@endsection
