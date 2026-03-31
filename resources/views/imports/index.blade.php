@extends('layouts.app')
@section('title', 'Imports')

@php
    $monthlyEnabled = $importStates['month']['enabled'] ?? false;
    $expenditureEnabled = $importStates['expenditure']['enabled'] ?? false;
    $yearEnabled = $importStates['year']['enabled'] ?? true;
@endphp

@section('content')
<div class="import-hub">
    <div class="card mb-6">
        <div class="card-head">
            <div class="card-title">Import Center</div>
        </div>
        <div class="card-body">
            <p class="text-mid" style="line-height:1.7;">
                Upload and preview your welfare spreadsheets in one place. Each import includes a live preview,
                smart error handling, and safe re-import logic to keep your data consistent.
            </p>
        </div>
    </div>

    <div class="import-card-grid">
        <div class="import-type-card">
            <div class="import-card-head">
                <div class="import-card-title">Year Import</div>
                <span class="badge badge-g">Primary</span>
            </div>
            <p class="import-card-desc">
                Upload the full annual ledger. The system detects the year, members, contributions, welfare, and expenses.
            </p>
            <button class="btn btn-primary" data-modal-open="year-import-modal">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                Import Year
            </button>
        </div>

        <div class="import-type-card {{ $monthlyEnabled ? '' : 'is-disabled' }}">
            <div class="import-card-head">
                <div class="import-card-title">Monthly Import</div>
                <span class="badge {{ $monthlyEnabled ? 'badge-b' : 'badge-mid' }}">{{ $monthlyEnabled ? 'Enabled' : 'Locked' }}</span>
            </div>
            <p class="import-card-desc">
                Download a pre-filled template, add monthly payments and welfare, then upload for instant preview.
            </p>
            @if(!$hasFinancialYears)
                <div class="import-disabled-note">
                    Please import a year first before accessing monthly or expenditure imports.
                </div>
            @endif
            <button class="btn btn-outline" data-modal-open="monthly-import-modal" {{ $monthlyEnabled ? '' : 'disabled' }}>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Import Month
            </button>
        </div>

        <div class="import-type-card {{ $expenditureEnabled ? '' : 'is-disabled' }}">
            <div class="import-card-head">
                <div class="import-card-title">Expenditure Import</div>
                <span class="badge {{ $expenditureEnabled ? 'badge-b' : 'badge-mid' }}">{{ $expenditureEnabled ? 'Enabled' : 'Locked' }}</span>
            </div>
            <p class="import-card-desc">
                Upload the expenditure template and preview monthly expense totals before saving.
            </p>
            @if(!$hasFinancialYears)
                <div class="import-disabled-note">
                    Please import a year first before accessing monthly or expenditure imports.
                </div>
            @endif
            <button class="btn btn-outline" data-modal-open="expenditure-import-modal" {{ $expenditureEnabled ? '' : 'disabled' }}>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 7h16"></path><path d="M4 12h16"></path><path d="M4 17h10"></path></svg>
                Import Expenditures
            </button>
        </div>
    </div>
</div>
@endsection

{{-- Import Modals --}}
<x-dashboard.import-modal
    title="Import Yearly Spreadsheet"
    importType="year"
    :previewTabs="['Summary', 'Members', 'Payments', 'Expenses', 'Errors']"
    uploadRoute="{{ route('imports.year.preview') }}"
    submitRoute="{{ route('imports.year.final') }}"
    lastUploadRoute="{{ route('import.preview.last') }}"
    :lastUpload="$importStates['year']['has_last_upload'] ? $importStates['year']['last_upload'] : null"
    :openOnError="$errors->has('spreadsheet')"
    dropLabel="Drop your .xlsx file here"
    importLabel="Import"
>
    <x-slot name="inlineNote">
        <div class="import-inline-note">
            Accepts <strong>.xlsx</strong> files up to 20MB. Parsing is dynamic and supports varying sheet structures (2022-2026).
        </div>
    </x-slot>
</x-dashboard.import-modal>

<x-dashboard.import-modal
    title="Import Expenditures"
    importType="expenditure"
    :previewTabs="['Summary', 'Expenses', 'Errors']"
    uploadRoute="{{ route('imports.expenditures.preview') }}"
    submitRoute="{{ route('imports.expenditures.final') }}"
    dropLabel="Drop your expenditures file here"
    importLabel="Import"
>
    <x-slot name="leftExtra">
        <div style="background:var(--surface);border-radius:var(--r-sm);padding:14px 16px;margin-bottom:14px;border:1px solid var(--border);">
            <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--mid);margin-bottom:10px;">
                Download Template
            </div>
            <div class="form-row">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Financial Year</label>
                    <select class="form-control import-select" data-role="template-year">
                        @foreach($financialYears as $yr)
                        <option value="{{ $yr }}" {{ $yr == $selectedYear ? 'selected' : '' }}>{{ $yr }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;display:flex;align-items:flex-end;">
                    <button type="button" class="btn btn-outline btn-sm" data-role="download-template" data-template-url="{{ url('/expenditures/template') }}" style="gap:6px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Download Template
                    </button>
                </div>
            </div>
        </div>
    </x-slot>
    <x-slot name="inlineNote">
        <div class="import-inline-note">
            Upload one year of expenditures at a time. The preview groups rows by month before saving.
        </div>
    </x-slot>
</x-dashboard.import-modal>

<x-dashboard.import-modal
    title="Import Monthly Payments"
    importType="monthly"
    :previewTabs="['Summary', 'Payments', 'Errors']"
    uploadRoute="{{ route('imports.preview.month') }}"
    submitRoute="{{ route('imports.final.month') }}"
    dropLabel="Drop filled template here"
    importLabel="Import Payments"
>
    <x-slot name="leftExtra">
        <div style="background:var(--surface);border-radius:var(--r-sm);padding:16px 18px;margin-bottom:18px;border:1px solid var(--border);">
            <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--mid);margin-bottom:12px;">
                Step 1 - Download Template
            </div>
            <div class="form-row">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Financial Year</label>
                    <select class="form-control import-select" data-role="template-year">
                        @for($yr = $minYear; $yr <= $maxYear; $yr++)
                        <option value="{{ $yr }}" {{ $yr == $selectedYear ? 'selected' : '' }}>{{ $yr }}</option>
                        @endfor
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Month</label>
                    <select class="form-control import-select" data-role="template-month">
                        @foreach(\App\Models\Payment::MONTHS as $n => $mn)
                        <option value="{{ $n }}" {{ $n == date('n') ? 'selected' : '' }}>{{ $mn }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div style="margin-top:12px;">
                <button type="button" class="btn btn-outline btn-sm" data-role="download-template" data-template-url="{{ route('import.monthly.template') }}" style="gap:6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download Template for Selected Month
                </button>
                <div class="text-sm text-mid" style="margin-top:6px;">
                    Pre-filled with all members. Green cells = existing payment, amber = existing welfare.
                </div>
            </div>
        </div>

        <input type="hidden" name="year" data-role="upload-year">
        <input type="hidden" name="month" data-role="upload-month">
    </x-slot>
    <x-slot name="inlineNote">
        <div style="margin-top:12px;padding:10px 12px;background:#fef3c7;border-radius:var(--r-sm);font-size:.78rem;color:#92400e;line-height:1.6;">
            <strong>Note:</strong> Members with an existing payment or welfare for the selected month will be skipped automatically - no overwriting.
        </div>
    </x-slot>
</x-dashboard.import-modal>
