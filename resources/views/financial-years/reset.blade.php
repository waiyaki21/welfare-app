@extends('layouts.app')
@section('title', 'Reset Database')

@section('content')
<div style="max-width:560px;margin:0 auto;">

    <div style="background:#fee2e2;border:1.5px solid #fca5a5;border-radius:var(--r);padding:22px 24px;margin-bottom:24px;">
        <div class="flex items-center gap-3" style="margin-bottom:12px;">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#991b1b" stroke-width="2" style="flex-shrink:0;">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            <div style="font-family:'DM Serif Display',serif;font-size:1.15rem;color:#991b1b;">
                This will permanently delete all data
            </div>
        </div>
        <p style="font-size:.875rem;color:#7f1d1d;line-height:1.65;">
            Resetting removes every member, payment, expense, welfare event, bank balance, and financial year record.
            Optionally you can also wipe user accounts and app settings. This action <strong>cannot be undone</strong>.
        </p>
    </div>

    {{-- What will be deleted --}}
    <div class="card mb-6">
        <div class="card-head"><div class="card-title">What will be deleted</div></div>
        <div class="card-body" style="padding:0;">
            <table>
                <thead><tr><th>Table</th><th>Records</th></tr></thead>
                <tbody>
                    <tr><td>Members</td>                   <td class="num">{{ number_format($counts['members']) }}</td></tr>
                    <tr><td>Financial Years</td>           <td class="num">{{ number_format($counts['financial_years']) }}</td></tr>
                    <tr><td>Payment Records</td>           <td class="num">{{ number_format($counts['payments']) }}</td></tr>
                    <tr><td>Expense Records</td>           <td class="num">{{ number_format($counts['expenses']) }}</td></tr>
                    <tr><td>Member Financial Summaries</td><td class="num">{{ number_format($counts['member_financials']) }}</td></tr>
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
                <div class="form-group" style="padding:14px 16px;background:#fff8f8;border-radius:var(--r-sm);border:1.5px solid #fca5a5;margin-bottom:18px;">
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
                    <input type="text" name="confirm_text" id="confirm-input" class="form-control"
                           placeholder="Type RESET here…"
                           autocomplete="off"
                           style="font-size:1rem;letter-spacing:.05em;"
                           oninput="checkConfirm()">
                    @error('confirm_text')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="flex gap-3">
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

@push('scripts')
<script>
function checkConfirm() {
    const val  = document.getElementById('confirm-input').value;
    const word = document.getElementById('reset-users-chk').checked ? 'RESET ALL' : 'RESET';
    document.getElementById('reset-btn').disabled = (val !== word);
}
function updateWarning(checked) {
    const word    = checked ? 'RESET ALL' : 'RESET';
    const btnLbl  = checked ? 'Reset Everything' : 'Reset Association Data';
    document.getElementById('confirm-word').textContent  = word;
    document.getElementById('reset-btn-label').textContent = btnLbl;
    document.getElementById('confirm-input').value = '';
    document.getElementById('reset-btn').disabled = true;
    document.getElementById('confirm-input').placeholder = `Type ${word} here\u2026`;
}
</script>
@endpush
@endsection
