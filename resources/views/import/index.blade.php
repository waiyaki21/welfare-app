@extends('layouts.app')
@section('title', 'Import Spreadsheet')

@section('content')
<div style="max-width:600px;margin:0 auto;">

@if(session('import_feedback') || session('import_results'))
@php
    $r = session('import_feedback', session('import_results'));
    $summary = $r['summary'] ?? $r;
@endphp
<div class="alert alert-success mb-6">
    <strong>Import complete!</strong>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:10px;font-size:.85rem;">
        <span>Sheets processed: <strong>{{ $summary['sheets_processed'] ?? 0 }}</strong></span>
        <span>Members created: <strong>{{ $summary['members_created'] ?? 0 }}</strong></span>
        <span>Members updated: <strong>{{ $summary['members_updated'] ?? 0 }}</strong></span>
        <span>Payments created: <strong>{{ $summary['payments_created'] ?? 0 }}</strong></span>
        <span>Expenses created: <strong>{{ $summary['expenses_created'] ?? 0 }}</strong></span>
        <span>Failed rows: <strong>{{ $summary['failed_rows'] ?? 0 }}</strong></span>
    </div>
    @if(!empty($r['errors']))
    <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--mint);">
        <strong>Warnings:</strong>
        @foreach($r['errors'] as $e)<div style="font-size:.8rem">• {{ $e }}</div>@endforeach
    </div>
    @endif
</div>
@endif

<div class="card mb-4">
    <div class="card-head"><div class="card-title">Import Financial Spreadsheet</div></div>
    <div class="card-body">
        <p style="color:var(--mid);font-size:.875rem;line-height:1.65;margin-bottom:20px;">
            Upload a yearly financial ledger (.xlsx). The importer will automatically detect the year from the sheet name,
            upsert members by name, create individual payment records for each monthly contribution,
            and parse operating expenses from the bank balance rows.
        </p>

        <form method="POST" action="{{ route('import.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label class="form-label">Spreadsheet File (.xlsx)</label>
                <input type="file" name="spreadsheet" accept=".xlsx,.xls" class="form-control" required>
                <div class="form-hint">Max 20MB · Supports .xlsx and .xls formats</div>
                @error('spreadsheet')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary w-full" style="justify-content:center;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                Import Spreadsheet
            </button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-head"><div class="card-title">Expected Format</div></div>
    <div class="card-body" style="font-size:.855rem;color:var(--mid);line-height:1.75;">
        <div style="margin-bottom:12px">The spreadsheet must follow the Athoni Welfare Association ledger format:</div>
        <div style="display:grid;gap:6px;">
            <div style="display:flex;gap:10px;align-items:baseline">
                <span style="width:8px;height:8px;border-radius:50%;background:var(--sage);flex-shrink:0;margin-top:4px;display:block"></span>
                Sheet name contains the year — e.g. <em>YEAR 2022</em>, <em>YEAR 2023</em>
            </div>
            <div style="display:flex;gap:10px;align-items:baseline">
                <span style="width:8px;height:8px;border-radius:50%;background:var(--sage);flex-shrink:0;margin-top:4px;display:block"></span>
                Header row: NO · MEMBERS NAME · Telephone No. · Total Contributions B/F
            </div>
            <div style="display:flex;gap:10px;align-items:baseline">
                <span style="width:8px;height:8px;border-radius:50%;background:var(--sage);flex-shrink:0;margin-top:4px;display:block"></span>
                Month columns: JANUARY through DECEMBER
            </div>
            <div style="display:flex;gap:10px;align-items:baseline">
                <span style="width:8px;height:8px;border-radius:50%;background:var(--sage);flex-shrink:0;margin-top:4px;display:block"></span>
                Totals: C/F · Total Welfare · Dev. · Welfare Owing · Total Investment
            </div>
            <div style="display:flex;gap:10px;align-items:baseline">
                <span style="width:8px;height:8px;border-radius:50%;background:var(--sage);flex-shrink:0;margin-top:4px;display:block"></span>
                Bank balance and expense rows at the bottom of each sheet
            </div>
        </div>
        <div style="margin-top:16px;padding:12px 14px;background:var(--surface);border-radius:var(--r-sm);border:1px solid var(--border);">
            <strong style="color:var(--ink)">Re-import safe:</strong> importing the same year again will update existing members
            and replace payment records — no duplicates are created.
        </div>
    </div>
</div>

</div>
@endsection
