@extends('layouts.app')
@section('title', 'Payments')

@section('topbar-actions')
<button onclick="document.getElementById('addModal').classList.add('open')" class="btn btn-primary btn-sm">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Add Payment
</button>
@endsection

@push('styles')
<style>
/* ── Heatmap cell ─────────────────────────────────────────────── */
.heat-cell {
    border-radius: 8px;
    padding: 10px 6px;
    text-align: center;
    border: 1.5px solid var(--border);
    transition: transform .12s;
    cursor: pointer;
    text-decoration: none;
    display: block;
    position: relative;
}
.heat-cell:hover { transform: scale(1.05); }
.heat-cell.active-month {
    border-color: var(--forest);
    box-shadow: 0 0 0 2px rgba(45,106,79,.18);
    outline: none;
}
.heat-cell.active-month::after {
    content: '';
    position: absolute;
    bottom: 4px;
    left: 50%;
    transform: translateX(-50%);
    width: 4px; height: 4px;
    border-radius: 50%;
    background: var(--forest);
}
.heat-label { font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--mid);margin-bottom:4px; }
.heat-val   { font-size:.82rem;font-weight:600;color:var(--soft); }
.heat-cell.has-data .heat-label { color:var(--mid); }
.heat-cell.has-data .heat-val   { color:var(--forest); }
.heat-cell.active-month .heat-label { color: var(--forest); font-weight:800; }

/* ── Table enhancements (shared with members page) ────────────── */
mark.hl { background:#fef08a;color:inherit;border-radius:2px;padding:0 1px; }
th.sortable { cursor:pointer;user-select:none;white-space:nowrap; }
th.sortable:hover { color:var(--forest); }
th.sortable .si { display:inline-flex;flex-direction:column;gap:1px;margin-left:5px;vertical-align:middle;opacity:.3;transition:opacity .12s; }
th.sortable:hover .si { opacity:.65; }
th.sortable.asc  .si, th.sortable.desc .si { opacity:1; }
th.sortable.asc  .au { opacity:1; } th.sortable.asc  .ad { opacity:.2; }
th.sortable.desc .au { opacity:.2; } th.sortable.desc .ad { opacity:1; }
.au,.ad { width:0;height:0;display:block;border-left:4px solid transparent;border-right:4px solid transparent; }
.au { border-bottom:5px solid currentColor; }
.ad { border-top:5px solid currentColor; }
.search-wrap { position:relative; }
.search-wrap .clr { position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--mid);font-size:14px;line-height:1;display:none;padding:2px 4px; }
.search-wrap .clr:hover { color:var(--ink); }
.search-wrap input:not(:placeholder-shown) ~ .clr { display:block; }
</style>
@endpush

@section('content')

{{-- ══ Heatmap ════════════════════════════════════════════════════ --}}
@if(!empty($monthlySummary))
<div class="card mb-6">
    <div class="card-head">
        <div class="card-title">{{ $selectedYear }} — Monthly Totals</div>
        <div class="flex items-center gap-2">
            @if($monthFilter)
                <span class="badge badge-b">Filtered: {{ \App\Models\Payment::MONTHS[$monthFilter] }}</span>
                <a href="{{ route('payments.index', ['year'=>$selectedYear]) }}" class="btn btn-ghost btn-xs">Show all months</a>
            @else
                <span class="badge badge-g">KES {{ number_format($yearTotal) }} total</span>
            @endif
        </div>
    </div>
    <div class="card-body" style="padding:16px;">
        @php
            $maxM   = !empty($monthlySummary) ? (max($monthlySummary) ?: 1) : 1;
            $months = \App\Models\Payment::MONTHS;
        @endphp
        <div style="display:grid;grid-template-columns:repeat(12,1fr);gap:6px;">
            @foreach($months as $n => $mn)
            @php
                $amt       = $monthlySummary[$n] ?? 0;
                $intensity = $amt > 0 ? round(($amt / $maxM) * 0.28, 2) : 0;
                $isActive  = (int)$monthFilter === $n;
                $cellUrl   = $isActive
                    ? route('payments.index', ['year'=>$selectedYear])
                    : route('payments.index', ['year'=>$selectedYear, 'month'=>$n]);
            @endphp
            <a href="{{ $cellUrl }}"
               class="heat-cell {{ $amt > 0 ? 'has-data' : '' }} {{ $isActive ? 'active-month' : '' }}"
               style="background:rgba(45,106,79,{{ $intensity }});border-color:{{ $isActive ? 'var(--forest)' : ($amt > 0 ? '#b7e4c7' : 'var(--border)') }};"
               title="{{ $mn }}: {{ $amt > 0 ? 'KES '.number_format($amt) : 'No payments' }}{{ $isActive ? ' (active filter)' : '' }}">
                <div class="heat-label">{{ substr($mn,0,3) }}</div>
                <div class="heat-val">{{ $amt > 0 ? number_format($amt) : '—' }}</div>
            </a>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- ══ Server filters (year / month / type) ══════════════════════ --}}
<div class="card">
    <div class="card-head" style="flex-wrap:wrap;gap:10px;">
        <div class="card-title">Payment Records</div>

        <div class="flex items-center gap-2" style="flex-wrap:wrap;flex:1;justify-content:flex-end;">

            {{-- Live search --}}
            <div class="search-wrap">
                <input type="text" id="live-search"
                       placeholder="Search member or notes…"
                       class="form-control"
                       style="width:210px;padding:7px 30px 7px 12px;"
                       autocomplete="off">
                <button class="clr" onclick="clearSearch()" title="Clear">✕</button>
            </div>

            {{-- Server-side filters --}}
            <form method="GET" id="filter-form" class="flex items-center gap-2" style="flex-wrap:wrap;">
                <select name="year" class="form-control" style="width:auto;padding:7px 28px 7px 10px;"
                        onchange="submitFilter()">
                    @foreach($years as $yr)
                    <option value="{{ $yr }}" {{ $yr==$selectedYear ? 'selected':'' }}>{{ $yr }}</option>
                    @endforeach
                </select>
                <select name="month" class="form-control" style="width:auto;padding:7px 28px 7px 10px;"
                        onchange="submitFilter()">
                    <option value="">All months</option>
                    @foreach(\App\Models\Payment::MONTHS as $n => $mn)
                    <option value="{{ $n }}" {{ $monthFilter==$n ? 'selected':'' }}>{{ $mn }}</option>
                    @endforeach
                </select>
                <select name="type" class="form-control" style="width:auto;padding:7px 28px 7px 10px;"
                        onchange="submitFilter()">
                    <option value="">All types</option>
                    @foreach(\App\Models\Payment::TYPES as $val => $lbl)
                    <option value="{{ $val }}" {{ $typeFilter==$val ? 'selected':'' }}>{{ $lbl }}</option>
                    @endforeach
                </select>
                @if($monthFilter || $typeFilter)
                <a href="{{ route('payments.index', ['year'=>$selectedYear]) }}" class="btn btn-ghost btn-sm">Clear</a>
                @endif
            </form>

            {{-- Rows per page --}}
            <div class="flex items-center gap-2">
                <label class="text-sm text-mid" style="white-space:nowrap">Show</label>
                <select id="rows-per-page" class="form-control" style="width:auto;padding:7px 28px 7px 10px;">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="30" selected>30</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="9999">All</option>
                </select>
                <label class="text-sm text-mid">rows</label>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="tbl-wrap">
        <table id="pay-table">
            <thead>
                <tr>
                    <th class="sortable" data-col="0" data-type="string">
                        Member <span class="si"><span class="au"></span><span class="ad"></span></span>
                    </th>
                    <th class="sortable" data-col="1" data-type="number">
                        Month <span class="si"><span class="au"></span><span class="ad"></span></span>
                    </th>
                    <th class="sortable" data-col="2" data-type="number">
                        Amount (KES) <span class="si"><span class="au"></span><span class="ad"></span></span>
                    </th>
                    <th class="sortable" data-col="3" data-type="string">
                        Type <span class="si"><span class="au"></span><span class="ad"></span></span>
                    </th>
                    <th class="sortable" data-col="4" data-type="number">
                        Year <span class="si"><span class="au"></span><span class="ad"></span></span>
                    </th>
                    <th class="sortable" data-col="5" data-type="string">
                        Notes <span class="si"><span class="au"></span><span class="ad"></span></span>
                    </th>
                    <th style="width:90px;"></th>
                </tr>
            </thead>
            <tbody id="pay-tbody">
            @forelse($payments as $pay)
            <tr class="pay-row"
                data-member="{{ strtolower($pay->member->name) }}"
                data-notes="{{ strtolower($pay->notes ?? '') }}">
                <td data-val="{{ $pay->member->name }}">
                    <a href="{{ route('members.show', $pay->member) }}"
                       style="font-weight:500;color:var(--forest);text-decoration:none"
                       class="pay-member">{{ $pay->member->short_name }}</a>
                </td>
                <td data-val="{{ $pay->month }}" style="font-weight:500">{{ $pay->month_name }}</td>
                <td data-val="{{ $pay->amount }}" class="num pos">{{ number_format($pay->amount) }}</td>
                <td data-val="{{ $pay->payment_type }}">
                    <span class="badge badge-mid">{{ $pay->type_name }}</span>
                </td>
                <td data-val="{{ $pay->financialYear->year }}" class="dim">{{ $pay->financialYear->year }}</td>
                <td data-val="{{ $pay->notes }}" class="dim text-sm pay-notes">{{ $pay->notes ?? '—' }}</td>
                <td>
                    <div class="flex gap-1">
                        <a href="{{ route('payments.edit', $pay) }}" class="btn btn-ghost btn-xs">Edit</a>
                        <form method="POST" action="{{ route('payments.destroy', $pay) }}"
                              onsubmit="return confirm('Delete this payment?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-xs" style="color:var(--rust)">Del</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr id="server-empty">
                <td colspan="7">
                    <div class="empty-state" style="padding:40px"><p>No payments found.</p></div>
                </td>
            </tr>
            @endforelse
            </tbody>
        </table>
        <div id="no-results" style="display:none;text-align:center;padding:40px;color:var(--mid);font-size:.875rem;">
            No payments match <strong id="no-results-term"></strong>
        </div>
    </div>

    {{-- Footer --}}
    <div class="card-foot flex items-center justify-between">
        <span class="text-sm dim" id="row-count"></span>
        <div id="client-pagination" class="flex items-center gap-1" style="flex-wrap:wrap;"></div>
    </div>
</div>

{{-- ══ Add Payment Modal ══════════════════════════════════════════ --}}
<div class="modal-backdrop" id="addModal">
    <div class="modal">
        <div class="modal-head">
            <div class="modal-title">Record New Payment</div>
            <button class="close-btn" onclick="closeAddModal()">✕</button>
        </div>
        <form method="POST" action="{{ route('payments.store') }}">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Member <span style="color:var(--rust)">*</span></label>
                    <select name="member_id" class="form-control" required>
                        <option value="">— Select member —</option>
                        @foreach($members as $m)
                        <option value="{{ $m->id }}" {{ old('member_id')==$m->id ? 'selected':'' }}>{{ $m->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Financial Year <span style="color:var(--rust)">*</span></label>
                        <select name="financial_year_id" class="form-control" required>
                            @foreach($fyAll as $fy)
                            <option value="{{ $fy->id }}" {{ $fy->year==$selectedYear ? 'selected':'' }}>{{ $fy->year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Month <span style="color:var(--rust)">*</span></label>
                        <select name="month" class="form-control" required>
                            @foreach(\App\Models\Payment::MONTHS as $n => $mn)
                            <option value="{{ $n }}" {{ $n==$monthFilter&&$monthFilter ? 'selected':($n==date('n')&&!$monthFilter ? 'selected':'') }}>{{ $mn }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Amount (KES) <span style="color:var(--rust)">*</span></label>
                        <input type="number" name="amount" class="form-control" placeholder="e.g. 2500" min="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select name="payment_type" class="form-control">
                            @foreach(\App\Models\Payment::TYPES as $v => $l)
                            <option value="{{ $v }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" placeholder="Optional…">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" onclick="closeAddModal()" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Payment</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    // ── State ─────────────────────────────────────────────────────
    let query       = '';
    let sortCol     = -1;
    let sortDir     = 'asc';
    let pageSize    = 30;
    let currentPage = 1;

    const tbody      = document.getElementById('pay-tbody');
    const noResults  = document.getElementById('no-results');
    const noResTerm  = document.getElementById('no-results-term');
    const rowCount   = document.getElementById('row-count');
    const pagination = document.getElementById('client-pagination');
    const rppSel     = document.getElementById('rows-per-page');
    const allRows    = Array.from(tbody?.querySelectorAll('tr.pay-row') ?? []);

    // ── Highlight ──────────────────────────────────────────────────
    function hl(text, term) {
        if (!term) return text;
        const esc = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        return text.replace(new RegExp(esc, 'gi'), m => `<mark class="hl">${m}</mark>`);
    }

    function applyHl(row, term) {
        const memberEl = row.querySelector('.pay-member');
        if (memberEl) {
            if (!memberEl.dataset.orig) memberEl.dataset.orig = memberEl.textContent;
            memberEl.innerHTML = hl(memberEl.dataset.orig, term);
        }
        const notesEl = row.querySelector('.pay-notes');
        if (notesEl) {
            if (!notesEl.dataset.orig) notesEl.dataset.orig = notesEl.textContent;
            notesEl.innerHTML = hl(notesEl.dataset.orig, term);
        }
    }

    // ── Filter ─────────────────────────────────────────────────────
    function filterRows() {
        if (!query) return allRows;
        return allRows.filter(r =>
            (r.dataset.member || '').includes(query) ||
            (r.dataset.notes  || '').includes(query)
        );
    }

    // ── Sort ───────────────────────────────────────────────────────
    function sortRows(rows) {
        if (sortCol < 0) return rows;
        const th   = document.querySelectorAll('th.sortable')[sortCol];
        const type = th?.dataset.type || 'string';
        return [...rows].sort((a, b) => {
            let va = a.querySelectorAll('td')[sortCol]?.dataset.val ?? '';
            let vb = b.querySelectorAll('td')[sortCol]?.dataset.val ?? '';
            if (type === 'number') { va = parseFloat(va)||0; vb = parseFloat(vb)||0; }
            else { va = va.toLowerCase(); vb = vb.toLowerCase(); }
            if (va < vb) return sortDir === 'asc' ? -1 : 1;
            if (va > vb) return sortDir === 'asc' ?  1 : -1;
            return 0;
        });
    }

    // ── Paginate ───────────────────────────────────────────────────
    function paginate(rows) {
        return pageSize >= 9000 ? rows : rows.slice((currentPage - 1) * pageSize, currentPage * pageSize);
    }

    function buildPager(total) {
        pagination.innerHTML = '';
        if (pageSize >= 9000 || total <= pageSize) return;
        const pages = Math.ceil(total / pageSize);

        const btn = (label, page, disabled, active) => {
            const el = document.createElement('button');
            el.textContent = label;
            el.disabled = disabled;
            el.style.cssText = `padding:4px 10px;border-radius:6px;font-size:.8rem;font-weight:500;
                border:1px solid ${active ? 'var(--leaf)':'var(--border)'};
                background:${active ? 'var(--leaf)':'transparent'};
                color:${active ? '#fff':'var(--mid)'};
                cursor:${disabled ? 'default':'pointer'};opacity:${disabled ? '.4':'1'};`;
            if (!disabled && !active) el.addEventListener('click', () => { currentPage = page; render(); });
            return el;
        };
        const ellipsis = () => { const s = document.createElement('span'); s.textContent='…'; s.style.cssText='padding:4px 6px;color:var(--mid);font-size:.8rem;'; return s; };

        pagination.appendChild(btn('←', currentPage - 1, currentPage === 1, false));
        let pts = [];
        if (pages <= 7) { pts = Array.from({length:pages},(_,i)=>i+1); }
        else {
            pts=[1];
            if (currentPage>3) pts.push('…');
            for (let p=Math.max(2,currentPage-1);p<=Math.min(pages-1,currentPage+1);p++) pts.push(p);
            if (currentPage<pages-2) pts.push('…');
            pts.push(pages);
        }
        pts.forEach(p => pagination.appendChild(p==='…' ? ellipsis() : btn(p,p,false,p===currentPage)));
        pagination.appendChild(btn('→', currentPage + 1, currentPage === pages, false));
    }

    // ── Render ─────────────────────────────────────────────────────
    function render() {
        const filtered = filterRows();
        const sorted   = sortRows(filtered);
        const paged    = paginate(sorted);

        allRows.forEach(r => r.style.display = 'none');

        if (paged.length === 0) {
            noResults.style.display = 'block';
            noResTerm.textContent = `"${query}"`;
            rowCount.textContent = '0 records';
            pagination.innerHTML = '';
        } else {
            noResults.style.display = 'none';
            paged.forEach(r => { r.style.display = ''; applyHl(r, query); });
            const total = filtered.length;
            const start = pageSize >= 9000 ? 1 : (currentPage-1)*pageSize+1;
            const end   = pageSize >= 9000 ? total : Math.min(currentPage*pageSize, total);
            rowCount.textContent = total === allRows.length
                ? `${total} records`
                : `${total} of ${allRows.length} · showing ${start}–${end}`;
            buildPager(total);
        }
    }

    // ── Events ─────────────────────────────────────────────────────
    let debounce;
    document.getElementById('live-search')?.addEventListener('input', function () {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            query = this.value.trim().toLowerCase();
            currentPage = 1;
            render();
        }, 180);
    });

    window.clearSearch = function () {
        const s = document.getElementById('live-search');
        if (s) { s.value = ''; query = ''; currentPage = 1; render(); s.focus(); }
    };

    document.querySelectorAll('th.sortable').forEach((th, idx) => {
        th.addEventListener('click', function () {
            sortDir = sortCol === idx ? (sortDir === 'asc' ? 'desc' : 'asc') : 'asc';
            sortCol = idx;
            document.querySelectorAll('th.sortable').forEach(t => t.classList.remove('asc','desc'));
            this.classList.add(sortDir);
            currentPage = 1;
            render();
        });
    });

    rppSel?.addEventListener('change', function () {
        pageSize = parseInt(this.value);
        currentPage = 1;
        render();
    });

    render();
})();

// ── Server filter helper ───────────────────────────────────────────
function submitFilter() { document.getElementById('filter-form').submit(); }

// ── Add modal ─────────────────────────────────────────────────────
function closeAddModal() { document.getElementById('addModal').classList.remove('open'); }
document.getElementById('addModal')?.addEventListener('click', e => { if (e.target.id==='addModal') closeAddModal(); });
document.addEventListener('keydown', e => { if (e.key==='Escape') closeAddModal(); });
</script>
@endpush
