@extends('layouts.app')
@section('title', 'Members')

@section('topbar-actions')
<a href="{{ route('members.create') }}" class="btn btn-primary btn-sm">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Add Member
</a>
@endsection

@push('styles')
<style>
/* ── Highlight ───────────────────────────────────── */
mark.hl {
    background: #fef08a;
    color: inherit;
    border-radius: 2px;
    padding: 0 1px;
}

/* ── Sortable headers ────────────────────────────── */
th.sortable {
    cursor: pointer;
    user-select: none;
    white-space: nowrap;
}
th.sortable:hover { color: var(--forest); }
th.sortable .sort-icon {
    display: inline-flex;
    flex-direction: column;
    gap: 1px;
    margin-left: 5px;
    vertical-align: middle;
    opacity: .35;
    transition: opacity .12s;
}
th.sortable:hover .sort-icon { opacity: .7; }
th.sortable.asc  .sort-icon,
th.sortable.desc .sort-icon { opacity: 1; }
th.sortable.asc  .sort-icon .arr-up   { opacity: 1; }
th.sortable.asc  .sort-icon .arr-down { opacity: .25; }
th.sortable.desc .sort-icon .arr-up   { opacity: .25; }
th.sortable.desc .sort-icon .arr-down { opacity: 1; }
.arr-up, .arr-down {
    width: 0; height: 0; display: block;
    border-left: 4px solid transparent;
    border-right: 4px solid transparent;
}
.arr-up   { border-bottom: 5px solid currentColor; }
.arr-down { border-top:    5px solid currentColor; }

/* ── Search input clear button ───────────────────── */
.search-wrap { position: relative; }
.search-wrap .clear-btn {
    position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer;
    color: var(--mid); font-size: 14px; line-height: 1;
    display: none; padding: 2px 4px;
}
.search-wrap .clear-btn:hover { color: var(--ink); }
.search-wrap input:not(:placeholder-shown) ~ .clear-btn { display: block; }

/* ── No-results row ──────────────────────────────── */
#no-results { display: none; }
</style>
@endpush

@section('content')

{{-- Stats row --}}
@if(!empty($stats))
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
    <div class="stat"><div class="stat-label">Total</div><div class="stat-value">{{ $stats['total'] }}</div><div class="stat-sub">on record</div></div>
    <div class="stat"><div class="stat-label">Active</div><div class="stat-value">{{ $stats['active'] }}</div><div class="stat-sub">members</div></div>
    <div class="stat green"><div class="stat-label">Surplus</div><div class="stat-value">{{ $stats['surplus'] }}</div><div class="stat-sub">in {{ request('year') ?? $selectedYear }}</div></div>
    <div class="stat {{ $stats['deficit'] > 0 ? 'red' : '' }}"><div class="stat-label">Deficit</div><div class="stat-value">{{ $stats['deficit'] }}</div><div class="stat-sub">welfare owed</div></div>
</div>
@endif

<div class="card">
    {{-- Filters bar --}}
    <div class="card-head" style="flex-wrap:wrap;gap:10px;">
        <div class="card-title">All Members</div>

        <div class="flex items-center gap-2" style="flex-wrap:wrap;flex:1;justify-content:flex-end;">

            {{-- Live search --}}
            <div class="search-wrap">
                <input type="text" id="live-search"
                       placeholder="Search name or phone…"
                       class="form-control"
                       style="width:220px;padding:7px 30px 7px 12px;"
                       value="{{ $search }}"
                       autocomplete="off">
                <button class="clear-btn" onclick="clearSearch()" title="Clear search">✕</button>
            </div>

            {{-- Server-side filters (year + status) — still need page reload --}}
            <form method="GET" id="filter-form" class="flex items-center gap-2">
                <input type="hidden" name="search" id="hidden-search" value="{{ $search }}">
                <select name="year" class="form-control" style="width:auto;padding:7px 28px 7px 10px;"
                        onchange="document.getElementById('filter-form').submit()">
                    @foreach($years as $yr)
                        <option value="{{ $yr }}" {{ $yr == $selectedYear ? 'selected':'' }}>{{ $yr }}</option>
                    @endforeach
                </select>
                <select name="filter" class="form-control" style="width:auto;padding:7px 28px 7px 10px;"
                        onchange="document.getElementById('filter-form').submit()">
                    <option value="all"      {{ $filter=='all'      ? 'selected':'' }}>All</option>
                    <option value="active"   {{ $filter=='active'   ? 'selected':'' }}>Active only</option>
                    <option value="inactive" {{ $filter=='inactive' ? 'selected':'' }}>Inactive</option>
                    <option value="surplus"  {{ $filter=='surplus'  ? 'selected':'' }}>Surplus</option>
                    <option value="deficit"  {{ $filter=='deficit'  ? 'selected':'' }}>Deficit</option>
                </select>
                @if($search || $filter !== 'all')
                    <a href="{{ route('members.index',['year'=>$selectedYear]) }}" class="btn btn-ghost btn-sm">Clear</a>
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

    <div class="tbl-wrap">
        <table id="members-table">
            <thead>
                <tr>
                    <th class="sortable" data-col="0" data-type="string">
                        Member <span class="sort-icon"><span class="arr-up"></span><span class="arr-down"></span></span>
                    </th>
                    <th class="sortable" data-col="1" data-type="string">
                        Phone <span class="sort-icon"><span class="arr-up"></span><span class="arr-down"></span></span>
                    </th>
                    <th class="sortable" data-col="2" data-type="number">
                        Joined <span class="sort-icon"><span class="arr-up"></span><span class="arr-down"></span></span>
                    </th>
                    <th class="sortable" data-col="3" data-type="number">
                        Contributions C/F <span class="sort-icon"><span class="arr-up"></span><span class="arr-down"></span></span>
                    </th>
                    <th class="sortable" data-col="4" data-type="number">
                        Welfare <span class="sort-icon"><span class="arr-up"></span><span class="arr-down"></span></span>
                    </th>
                    <th class="sortable" data-col="5" data-type="number">
                        Investment <span class="sort-icon"><span class="arr-up"></span><span class="arr-down"></span></span>
                    </th>
                    <th class="sortable" data-col="6" data-type="string">
                        Status <span class="sort-icon"><span class="arr-up"></span><span class="arr-down"></span></span>
                    </th>
                    <th style="width:100px"></th>
                </tr>
            </thead>
            <tbody id="members-tbody">
                @forelse($members as $member)
                @php $fin = $member->financials->first(); @endphp
                <tr class="member-row"
                    data-name="{{ strtolower($member->name) }}"
                    data-phone="{{ $member->phone }}">
                    <td data-val="{{ $member->name }}">
                        <div class="flex items-center gap-3">
                            <div class="avatar avatar-sm" style="{{ !$member->is_active ? 'opacity:.5' : '' }}">{{ $member->initials }}</div>
                            <div>
                                <a href="{{ route('members.show', $member->id) }}"
                                   style="font-weight:500;color:var(--forest);text-decoration:none;"
                                   class="member-name">{{ $member->name }}</a>
                                @if(!$member->is_active)
                                    <span class="badge badge-mid" style="margin-left:6px;font-size:.65rem">Inactive</span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="dim text-sm member-phone" data-val="{{ $member->phone }}">{{ $member->phone ?? '—' }}</td>
                    <td class="dim text-sm" data-val="{{ $member->joined_year ?? 0 }}">{{ $member->joined_year ?? '—' }}</td>
                    @if($fin)
                        <td class="num" data-val="{{ $fin->contributions_carried_forward }}">{{ number_format($fin->contributions_carried_forward) }}</td>
                        <td class="num" data-val="{{ $fin->total_welfare }}">{{ number_format($fin->total_welfare) }}</td>
                        <td class="num {{ $fin->total_investment > 0 ? 'pos' : ($fin->total_investment < 0 ? 'neg' : '') }}"
                            data-val="{{ $fin->total_investment }}">{{ number_format($fin->total_investment) }}</td>
                        <td data-val="{{ $fin->welfare_owing >= 0 ? 'surplus' : 'deficit' }}">
                            @if($fin->welfare_owing >= 0)
                                <span class="badge badge-g">Surplus</span>
                            @else
                                <span class="badge badge-r">Deficit {{ number_format(abs($fin->welfare_owing)) }}</span>
                            @endif
                        </td>
                    @else
                        <td data-val="0">—</td>
                        <td data-val="0">—</td>
                        <td data-val="0">—</td>
                        <td data-val="">—</td>
                    @endif
                    <td>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('members.show', $member->id) }}" class="btn btn-ghost btn-xs">View</a>
                            <a href="{{ route('members.edit', $member) }}" class="btn btn-ghost btn-xs">Edit</a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr id="empty-server">
                    <td colspan="8">
                        <div class="empty-state" style="padding:40px">
                            <p>No members found{{ $search ? " matching \"$search\"" : '' }}.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Client-side no-results row --}}
        <div id="no-results" style="text-align:center;padding:40px;color:var(--mid);display:none;">
            No members match <strong id="no-results-term"></strong>
        </div>
    </div>

    {{-- Footer: count + pagination --}}
    <div class="card-foot flex items-center justify-between">
        <span class="text-sm dim" id="row-count"></span>
        <div id="client-pagination" class="flex items-center gap-1" style="flex-wrap:wrap;"></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // ── State ─────────────────────────────────────────────────────────────
    let query      = '';
    let sortCol    = -1;
    let sortDir    = 'asc';  // 'asc' | 'desc'
    let pageSize   = 30;
    let currentPage= 1;

    // ── DOM refs ──────────────────────────────────────────────────────────
    const searchInput  = document.getElementById('live-search');
    const hiddenSearch = document.getElementById('hidden-search');
    const tbody        = document.getElementById('members-tbody');
    const noResults    = document.getElementById('no-results');
    const noResultsTerm= document.getElementById('no-results-term');
    const rowCount     = document.getElementById('row-count');
    const pagination   = document.getElementById('client-pagination');
    const rppSelect    = document.getElementById('rows-per-page');
    const allRows      = Array.from(tbody.querySelectorAll('tr.member-row'));

    // ── Highlight helper ──────────────────────────────────────────────────
    function highlight(text, term) {
        if (!term) return text;
        const escaped = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        return text.replace(new RegExp(escaped, 'gi'),
            m => `<mark class="hl">${m}</mark>`);
    }

    function applyHighlight(row, term) {
        // Name cell
        const nameEl = row.querySelector('.member-name');
        if (nameEl) {
            if (!nameEl.dataset.original) nameEl.dataset.original = nameEl.textContent;
            nameEl.innerHTML = highlight(nameEl.dataset.original, term);
        }
        // Phone cell
        const phoneEl = row.querySelector('.member-phone');
        if (phoneEl) {
            if (!phoneEl.dataset.original) phoneEl.dataset.original = phoneEl.textContent;
            phoneEl.innerHTML = highlight(phoneEl.dataset.original, term);
        }
    }

    // ── Filter rows by search query ───────────────────────────────────────
    function filterRows() {
        return allRows.filter(row => {
            if (!query) return true;
            const name  = row.dataset.name  || '';
            const phone = row.dataset.phone || '';
            return name.includes(query.toLowerCase()) || phone.includes(query);
        });
    }

    // ── Sort filtered rows ────────────────────────────────────────────────
    function sortRows(rows) {
        if (sortCol < 0) return rows;
        const th   = document.querySelectorAll('th.sortable')[sortCol];
        const type = th?.dataset.type || 'string';

        return [...rows].sort((a, b) => {
            const cellA = a.querySelectorAll('td')[sortCol];
            const cellB = b.querySelectorAll('td')[sortCol];
            let valA = cellA?.dataset.val ?? '';
            let valB = cellB?.dataset.val ?? '';

            if (type === 'number') {
                valA = parseFloat(valA) || 0;
                valB = parseFloat(valB) || 0;
            } else {
                valA = valA.toLowerCase();
                valB = valB.toLowerCase();
            }

            if (valA < valB) return sortDir === 'asc' ? -1 : 1;
            if (valA > valB) return sortDir === 'asc' ? 1 : -1;
            return 0;
        });
    }

    // ── Paginate ──────────────────────────────────────────────────────────
    function paginate(rows) {
        if (pageSize >= 9000) return rows; // "All"
        const start = (currentPage - 1) * pageSize;
        return rows.slice(start, start + pageSize);
    }

    function buildPagination(total) {
        pagination.innerHTML = '';
        if (pageSize >= 9000 || total <= pageSize) return;

        const pages = Math.ceil(total / pageSize);

        const btn = (label, page, disabled, active) => {
            const el = document.createElement('button');
            el.textContent = label;
            el.disabled = disabled;
            el.style.cssText = `
                padding:4px 10px;border-radius:6px;font-size:.8rem;font-weight:500;
                border:1px solid ${active ? 'var(--leaf)' : 'var(--border)'};
                background:${active ? 'var(--leaf)' : 'transparent'};
                color:${active ? '#fff' : 'var(--mid)'};
                cursor:${disabled ? 'default' : 'pointer'};
                opacity:${disabled ? '.4' : '1'};
            `;
            if (!disabled && !active) {
                el.addEventListener('click', () => { currentPage = page; render(); });
            }
            return el;
        };

        pagination.appendChild(btn('←', currentPage - 1, currentPage === 1, false));

        // show up to 7 page buttons with ellipsis
        let pagesToShow = [];
        if (pages <= 7) {
            pagesToShow = Array.from({length: pages}, (_, i) => i + 1);
        } else {
            pagesToShow = [1];
            if (currentPage > 3) pagesToShow.push('…');
            for (let p = Math.max(2, currentPage-1); p <= Math.min(pages-1, currentPage+1); p++) pagesToShow.push(p);
            if (currentPage < pages - 2) pagesToShow.push('…');
            pagesToShow.push(pages);
        }

        pagesToShow.forEach(p => {
            if (p === '…') {
                const el = document.createElement('span');
                el.textContent = '…';
                el.style.cssText = 'padding:4px 6px;color:var(--mid);font-size:.8rem;';
                pagination.appendChild(el);
            } else {
                pagination.appendChild(btn(p, p, false, p === currentPage));
            }
        });

        pagination.appendChild(btn('→', currentPage + 1, currentPage === pages, false));
    }

    // ── Master render ──────────────────────────────────────────────────────
    function render() {
        const filtered = filterRows();
        const sorted   = sortRows(filtered);
        const paged    = paginate(sorted);

        // Hide all rows first
        allRows.forEach(r => { r.style.display = 'none'; });

        if (paged.length === 0) {
            noResults.style.display = 'block';
            noResultsTerm.textContent = `"${query}"`;
            rowCount.textContent = '0 members';
            pagination.innerHTML = '';
        } else {
            noResults.style.display = 'none';
            paged.forEach(row => {
                row.style.display = '';
                applyHighlight(row, query);
            });

            const total = filtered.length;
            const start = pageSize >= 9000 ? 1 : (currentPage - 1) * pageSize + 1;
            const end   = pageSize >= 9000 ? total : Math.min(currentPage * pageSize, total);
            rowCount.textContent = total === allRows.length
                ? `${total} members`
                : `${total} of ${allRows.length} members · showing ${start}–${end}`;

            buildPagination(total);
        }
    }

    // ── Event: live search ────────────────────────────────────────────────
    let debounceTimer;
    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            query = this.value.trim();
            hiddenSearch.value = query;
            currentPage = 1;
            render();
        }, 180); // 180ms debounce — feels instant but doesn't fire every keystroke
    });

    // ── Event: clear search ───────────────────────────────────────────────
    window.clearSearch = function () {
        searchInput.value = '';
        query = '';
        hiddenSearch.value = '';
        currentPage = 1;
        render();
        searchInput.focus();
    };

    // ── Event: column sort ────────────────────────────────────────────────
    document.querySelectorAll('th.sortable').forEach((th, idx) => {
        th.addEventListener('click', function () {
            if (sortCol === idx) {
                sortDir = sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                sortCol = idx;
                sortDir = 'asc';
            }
            // Update header classes
            document.querySelectorAll('th.sortable').forEach(t => t.classList.remove('asc', 'desc'));
            this.classList.add(sortDir);
            currentPage = 1;
            render();
        });
    });

    // ── Event: rows per page ──────────────────────────────────────────────
    rppSelect.addEventListener('change', function () {
        pageSize = parseInt(this.value);
        currentPage = 1;
        render();
    });

    // ── Initial render ────────────────────────────────────────────────────
    // Apply any server-side search term that came in via URL
    query = searchInput.value.trim();
    render();
})();
</script>
@endpush
