@extends('layouts.app')
@section('title', 'Expenses')

@section('topbar-actions')
<a href="{{ route('expense-categories.index') }}" class="btn btn-outline btn-sm">Manage Categories</a>
<button onclick="document.getElementById('catModal').classList.add('open')" class="btn btn-outline btn-sm">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Add Category
</button>
<button onclick="document.getElementById('addModal').classList.add('open')" class="btn btn-primary btn-sm">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Add Expense
</button>
@endsection

@push('styles')
<style>
mark.hl{background:#fef08a;color:inherit;border-radius:2px;padding:0 1px;}
th.sortable{cursor:pointer;user-select:none;white-space:nowrap;}
th.sortable:hover{color:var(--forest);}
th.sortable .si{display:inline-flex;flex-direction:column;gap:1px;margin-left:5px;vertical-align:middle;opacity:.3;transition:opacity .12s;}
th.sortable:hover .si{opacity:.65;}
th.sortable.asc .si,th.sortable.desc .si{opacity:1;}
th.sortable.asc .au{opacity:1;}th.sortable.asc .ad{opacity:.2;}
th.sortable.desc .au{opacity:.2;}th.sortable.desc .ad{opacity:1;}
.au,.ad{width:0;height:0;display:block;border-left:4px solid transparent;border-right:4px solid transparent;}
.au{border-bottom:5px solid currentColor;}.ad{border-top:5px solid currentColor;}
.sw{position:relative;}
.sw .clr{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--mid);font-size:14px;line-height:1;display:none;padding:2px 4px;}
.sw .clr:hover{color:var(--ink);}
.sw input:not(:placeholder-shown)~.clr{display:block;}
</style>
@endpush

@section('content')

{{-- Category stat cards --}}
@if(!empty($byCat))
<div class="stats-grid mb-6" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));">
    <div class="stat dark">
        <div class="stat-label">Total Expenses</div>
        <div class="stat-value">{{ number_format($yearTotal) }}</div>
        <div class="stat-sub">KES in {{ $selectedYear }}</div>
    </div>
    @foreach($byCat as $cat => $amt)
    @php
        $catModel = $categories->firstWhere('slug', $cat);
        $catName  = $catModel ? $catModel->name : ucwords(str_replace('_',' ',$cat));
        $catColor = $catModel ? $catModel->color : '#fef3c7';
    @endphp
    <div class="stat" style="border-left:3px solid {{ $catColor }};border-radius:0 var(--r) var(--r) 0;">
        <div class="stat-label">{{ $catName }}</div>
        <div class="stat-value" style="font-size:1.2rem;">{{ number_format($amt) }}</div>
        <div class="stat-sub">{{ $yearTotal > 0 ? round($amt/$yearTotal*100,1) : 0 }}%</div>
    </div>
    @endforeach
</div>
@endif

{{-- Monthly chart --}}
@if(!empty($byMonth))
<div class="card mb-6">
    <div class="card-head"><div class="card-title">Monthly Expenses — {{ $selectedYear }}</div></div>
    <div class="card-body" style="padding:16px;">
        <div style="position:relative;height:160px;"><canvas id="expChart"></canvas></div>
    </div>
</div>
@endif

{{-- Table card --}}
<div class="card">
    <div class="card-head" style="flex-wrap:wrap;gap:10px;">
        <div class="card-title">Expense Records</div>

        <div class="flex items-center gap-2" style="flex-wrap:wrap;flex:1;justify-content:flex-end;">
            {{-- Live search --}}
            <div class="sw">
                <input type="text" id="live-search" placeholder="Search notes or category…"
                       class="form-control" style="width:210px;padding:7px 30px 7px 12px;" autocomplete="off">
                <button class="clr" onclick="clearSearch()" title="Clear">✕</button>
            </div>

            {{-- Server filters --}}
            <form method="GET" id="filter-form" class="flex items-center gap-2" style="flex-wrap:wrap;">
                <select name="year" class="form-control" style="width:auto;padding:7px 28px 7px 10px;" onchange="this.form.submit()">
                    @foreach($years as $yr)
                    <option value="{{ $yr }}" {{ $yr==$selectedYear ? 'selected':'' }}>{{ $yr }}</option>
                    @endforeach
                </select>
                <select name="month" class="form-control" style="width:auto;padding:7px 28px 7px 10px;" onchange="this.form.submit()">
                    <option value="">All months</option>
                    @foreach(\App\Models\Payment::MONTHS as $n => $mn)
                    <option value="{{ $n }}" {{ $monthFilter==$n ? 'selected':'' }}>{{ $mn }}</option>
                    @endforeach
                </select>
                <select name="category" class="form-control" style="width:auto;padding:7px 28px 7px 10px;" onchange="this.form.submit()">
                    <option value="">All categories</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->slug }}" {{ $catFilter==$cat->slug ? 'selected':'' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
                @if($monthFilter || $catFilter)
                <a href="{{ route('expenses.index', ['year'=>$selectedYear]) }}" class="btn btn-ghost btn-sm">Clear</a>
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
        <table id="exp-table">
            <thead>
                <tr>
                    <th class="sortable" data-col="0" data-type="number">Month <span class="si"><span class="au"></span><span class="ad"></span></span></th>
                    <th class="sortable" data-col="1" data-type="string">Category <span class="si"><span class="au"></span><span class="ad"></span></span></th>
                    <th class="sortable" data-col="2" data-type="number">Amount (KES) <span class="si"><span class="au"></span><span class="ad"></span></span></th>
                    <th class="sortable" data-col="3" data-type="number">Year <span class="si"><span class="au"></span><span class="ad"></span></span></th>
                    <th class="sortable" data-col="4" data-type="string">Notes <span class="si"><span class="au"></span><span class="ad"></span></span></th>
                    <th style="width:90px"></th>
                </tr>
            </thead>
            <tbody id="exp-tbody">
            @forelse($expenses as $exp)
            @php
                $catModel  = $categories->firstWhere('slug', $exp->category);
                $catName   = $catModel ? $catModel->name : $exp->category_name;
                $catColor  = $catModel ? $catModel->color : '#fef3c7';
            @endphp
            <tr class="exp-row"
                data-category="{{ strtolower($catName) }}"
                data-notes="{{ strtolower($exp->notes ?? '') }}">
                <td data-val="{{ $exp->month }}" style="font-weight:500">{{ $exp->month_name }}</td>
                <td data-val="{{ $catName }}">
                    <span class="badge exp-cat" style="background:{{ $catColor }};color:#1a1a1a;">{{ $catName }}</span>
                </td>
                <td data-val="{{ $exp->amount }}" class="num neg">{{ number_format($exp->amount) }}</td>
                <td data-val="{{ $exp->financialYear->year }}" class="dim">{{ $exp->financialYear->year }}</td>
                <td data-val="{{ $exp->notes }}" class="dim text-sm exp-notes">{{ $exp->notes ?? '—' }}</td>
                <td>
                    <div class="flex gap-1">
                        <a href="{{ route('expenses.edit', $exp) }}" class="btn btn-ghost btn-xs">Edit</a>
                        <form method="POST" action="{{ route('expenses.destroy', $exp) }}" onsubmit="return confirm('Delete this expense?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-xs" style="color:var(--rust)">Del</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr id="server-empty">
                <td colspan="6"><div class="empty-state" style="padding:40px"><p>No expenses found.</p></div></td>
            </tr>
            @endforelse
            </tbody>
        </table>
        <div id="no-results" style="display:none;text-align:center;padding:40px;color:var(--mid);font-size:.875rem;">
            No expenses match <strong id="no-results-term"></strong>
        </div>
    </div>

    <div class="card-foot flex items-center justify-between">
        <span class="text-sm dim" id="row-count"></span>
        <div id="client-pagination" class="flex items-center gap-1" style="flex-wrap:wrap;"></div>
    </div>
</div>

{{-- Add Expense Modal --}}
<div class="modal-backdrop" id="addModal">
    <div class="modal">
        <div class="modal-head">
            <div class="modal-title">Record Expense</div>
            <button class="close-btn" onclick="closeModal('addModal')">✕</button>
        </div>
        <form method="POST" action="{{ route('expenses.store') }}">
            @csrf
            <div class="modal-body">
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
                            <option value="{{ $n }}" {{ $n==date('n') ? 'selected':'' }}>{{ $mn }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Category <span style="color:var(--rust)">*</span></label>
                        <select name="category" class="form-control" required>
                            <option value="">— Select —</option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat->slug }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Amount (KES) <span style="color:var(--rust)">*</span></label>
                        <input type="number" name="amount" class="form-control" placeholder="e.g. 10000" min="0.01" step="0.01" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" placeholder="Optional details…">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" onclick="closeModal('addModal')" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Expense</button>
            </div>
        </form>
    </div>
</div>

{{-- Add Category Modal --}}
<div class="modal-backdrop" id="catModal">
    <div class="modal" style="max-width:420px;">
        <div class="modal-head">
            <div class="modal-title">Add Expense Category</div>
            <button class="close-btn" onclick="closeModal('catModal')">✕</button>
        </div>
        <form method="POST" action="{{ route('expense-categories.store') }}">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Category Name <span style="color:var(--rust)">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Legal Retainer" required>
                    <div class="form-hint">A unique identifier (slug) is generated automatically</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Badge Colour</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="color" value="#fef3c7"
                               style="width:44px;height:36px;padding:2px;border:1.5px solid var(--border);border-radius:var(--r-sm);cursor:pointer;background:none;">
                        <span class="text-sm dim">Background colour for the category badge</span>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" onclick="closeModal('catModal')" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Category</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
// ── Modal helpers ──────────────────────────────────────────────────
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-backdrop').forEach(el => {
    el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); });
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.querySelectorAll('.modal-backdrop.open').forEach(el => el.classList.remove('open'));
});

// ── Monthly chart ──────────────────────────────────────────────────
@if(!empty($byMonth))
const byMonthData = @json($byMonth);
const monthData = [];
for (let m = 1; m <= 12; m++) monthData.push(byMonthData[m] || 0);
new Chart(document.getElementById('expChart'), {
    type: 'bar',
    data: {
        labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
        datasets: [{ label: 'Expenses', data: monthData, backgroundColor: '#fee2e266', borderColor: '#c0392b', borderWidth: 1.5, borderRadius: 4 }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend:{display:false}, tooltip:{callbacks:{label: c=>'KES '+Math.round(c.raw).toLocaleString()}}},
        scales: {
            x: { grid:{display:false}, ticks:{font:{size:10}} },
            y: { grid:{color:'#f3f4f6'}, ticks:{font:{size:10}, callback: v=>v>=1000?(v/1000)+'k':v} }
        }
    }
});
@endif

// ── Live table ─────────────────────────────────────────────────────
(function () {
    let query='', sortCol=-1, sortDir='asc', pageSize=30, page=1;

    const tbody    = document.getElementById('exp-tbody');
    const noRes    = document.getElementById('no-results');
    const noTerm   = document.getElementById('no-results-term');
    const rowCount = document.getElementById('row-count');
    const pager    = document.getElementById('client-pagination');
    const rpp      = document.getElementById('rows-per-page');
    const allRows  = Array.from(tbody?.querySelectorAll('tr.exp-row') ?? []);

    function hl(t, q) {
        if (!q) return t;
        return t.replace(new RegExp(q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'),'gi'), m=>`<mark class="hl">${m}</mark>`);
    }
    function applyHl(row, q) {
        ['exp-cat','exp-notes'].forEach(cls => {
            const el = row.querySelector('.'+cls);
            if (!el) return;
            if (!el.dataset.orig) el.dataset.orig = el.textContent;
            el.innerHTML = hl(el.dataset.orig, q);
        });
    }
    function filter() {
        return !query ? allRows : allRows.filter(r =>
            (r.dataset.category||'').includes(query) || (r.dataset.notes||'').includes(query)
        );
    }
    function sort(rows) {
        if (sortCol < 0) return rows;
        const type = document.querySelectorAll('th.sortable')[sortCol]?.dataset.type || 'string';
        return [...rows].sort((a,b) => {
            let va=a.querySelectorAll('td')[sortCol]?.dataset.val??'';
            let vb=b.querySelectorAll('td')[sortCol]?.dataset.val??'';
            if (type==='number'){va=parseFloat(va)||0;vb=parseFloat(vb)||0;}
            else{va=va.toLowerCase();vb=vb.toLowerCase();}
            return (va<vb?-1:va>vb?1:0)*(sortDir==='asc'?1:-1);
        });
    }
    function paginate(rows) { return pageSize>=9000?rows:rows.slice((page-1)*pageSize,page*pageSize); }
    function buildPager(total) {
        pager.innerHTML='';
        if (pageSize>=9000||total<=pageSize) return;
        const pages=Math.ceil(total/pageSize);
        const btn=(lbl,p,dis,act)=>{
            const el=document.createElement('button');
            el.textContent=lbl;el.disabled=dis;
            el.style.cssText=`padding:4px 10px;border-radius:6px;font-size:.8rem;font-weight:500;border:1px solid ${act?'var(--leaf)':'var(--border)'};background:${act?'var(--leaf)':'transparent'};color:${act?'#fff':'var(--mid)'};cursor:${dis?'default':'pointer'};opacity:${dis?.4:1};`;
            if(!dis&&!act) el.addEventListener('click',()=>{page=p;render();});
            return el;
        };
        const dots=()=>{const s=document.createElement('span');s.textContent='…';s.style.cssText='padding:4px 6px;color:var(--mid);font-size:.8rem;';return s;};
        pager.appendChild(btn('←',page-1,page===1,false));
        let pts=pages<=7?Array.from({length:pages},(_,i)=>i+1):([1,...((page>3)?['…']:[]),...Array.from({length:Math.min(pages-1,page+1)-Math.max(2,page-1)+1},(_,i)=>Math.max(2,page-1)+i),...((page<pages-2)?['…']:[]),pages]);
        pts.forEach(p=>pager.appendChild(p==='…'?dots():btn(p,p,false,p===page)));
        pager.appendChild(btn('→',page+1,page===pages,false));
    }
    function render() {
        const filt=filter(), sorted=sort(filt), paged=paginate(sorted);
        allRows.forEach(r=>r.style.display='none');
        if (!paged.length){
            noRes.style.display='block';noTerm.textContent=`"${query}"`;
            rowCount.textContent='0 records';pager.innerHTML='';
        } else {
            noRes.style.display='none';
            paged.forEach(r=>{r.style.display='';applyHl(r,query);});
            const total=filt.length,start=pageSize>=9000?1:(page-1)*pageSize+1,end=pageSize>=9000?total:Math.min(page*pageSize,total);
            rowCount.textContent=total===allRows.length?`${total} records`:`${total} of ${allRows.length} · showing ${start}–${end}`;
            buildPager(total);
        }
    }

    let debounce;
    document.getElementById('live-search')?.addEventListener('input', function(){
        clearTimeout(debounce);
        debounce=setTimeout(()=>{query=this.value.trim().toLowerCase();page=1;render();},180);
    });
    window.clearSearch=()=>{const s=document.getElementById('live-search');if(s){s.value='';query='';page=1;render();s.focus();}};
    document.querySelectorAll('th.sortable').forEach((th,idx)=>{
        th.addEventListener('click',function(){
            sortDir=sortCol===idx?(sortDir==='asc'?'desc':'asc'):'asc';
            sortCol=idx;
            document.querySelectorAll('th.sortable').forEach(t=>t.classList.remove('asc','desc'));
            this.classList.add(sortDir);page=1;render();
        });
    });
    rpp?.addEventListener('change',function(){pageSize=parseInt(this.value);page=1;render();});
    render();
})();
</script>
@endpush
