const normalize = (value) => String(value ?? '').toLowerCase().trim();

const parseNumber = (value) => {
    const cleaned = String(value ?? '').replace(/[^0-9.-]/g, '');
    if (cleaned === '' || cleaned === '-' || cleaned === '.') return null;
    const num = Number(cleaned);
    return Number.isNaN(num) ? null : num;
};

const compareValues = (a, b) => {
    const numA = parseNumber(a);
    const numB = parseNumber(b);
    if (numA !== null && numB !== null) {
        return numA - numB;
    }
    return String(a ?? '').localeCompare(String(b ?? ''), undefined, { sensitivity: 'base' });
};

const initTable = (table) => {
    const body = table.querySelector('[data-table-body]');
    if (!body) return;

    const rows = Array.from(body.querySelectorAll('[data-table-row]')).map((row) => {
        let values = {};
        try {
            values = JSON.parse(row.dataset.rowValues || '{}');
        } catch (e) {
            values = {};
        }
        return {
            el: row,
            values,
            search: normalize(row.textContent),
        };
    });

    const state = {
        query: '',
        sortKey: null,
        sortDir: 'asc',
        perPage: Number(table.dataset.perPage || 10),
        page: 1,
    };

    const searchInput = table.querySelector('[data-table-search]');
    const pageSize = table.querySelector('[data-table-size]');
    const prevBtn = table.querySelector('[data-table-prev]');
    const nextBtn = table.querySelector('[data-table-next]');
    const pageInfo = table.querySelector('[data-table-info]');
    const emptyRow = table.querySelector('[data-table-empty]');

    const updateSortIndicators = () => {
        table.querySelectorAll('[data-sort-indicator]').forEach((indicator) => {
            indicator.textContent = '';
        });
        if (!state.sortKey) return;
        const active = table.querySelector(`[data-sort-key="${CSS.escape(state.sortKey)}"] [data-sort-indicator]`);
        if (active) {
            active.textContent = state.sortDir === 'asc' ? '▲' : '▼';
        }
    };

    const applyFilters = () => {
        let filtered = rows;
        if (state.query) {
            filtered = rows.filter((row) => row.search.includes(state.query));
        }

        if (state.sortKey) {
            filtered = [...filtered].sort((a, b) => {
                const valueA = a.values?.[state.sortKey] ?? '';
                const valueB = b.values?.[state.sortKey] ?? '';
                const result = compareValues(valueA, valueB);
                return state.sortDir === 'asc' ? result : -result;
            });
        }

        return filtered;
    };

    const render = () => {
        const filtered = applyFilters();
        const totalPages = Math.max(1, Math.ceil(filtered.length / state.perPage));
        if (state.page > totalPages) state.page = totalPages;
        if (state.page < 1) state.page = 1;

        const start = (state.page - 1) * state.perPage;
        const pageRows = filtered.slice(start, start + state.perPage);

        body.innerHTML = '';
        const fragment = document.createDocumentFragment();
        pageRows.forEach((row) => fragment.appendChild(row.el));

        if (pageRows.length === 0 && emptyRow) {
            fragment.appendChild(emptyRow);
        }

        body.appendChild(fragment);

        if (emptyRow) {
            emptyRow.style.display = pageRows.length === 0 ? '' : 'none';
        }

        if (pageInfo) {
            pageInfo.textContent = `Page ${state.page} of ${totalPages}`;
        }
        if (prevBtn) prevBtn.disabled = state.page <= 1;
        if (nextBtn) nextBtn.disabled = state.page >= totalPages;

        updateSortIndicators();
    };

    searchInput?.addEventListener('input', (event) => {
        state.query = normalize(event.target.value);
        state.page = 1;
        render();
    });

    pageSize?.addEventListener('change', (event) => {
        state.perPage = Number(event.target.value || state.perPage);
        state.page = 1;
        render();
    });

    prevBtn?.addEventListener('click', () => {
        state.page -= 1;
        render();
    });

    nextBtn?.addEventListener('click', () => {
        state.page += 1;
        render();
    });

    table.querySelectorAll('[data-sort-key]').forEach((th) => {
        th.addEventListener('click', () => {
            const key = th.dataset.sortKey || null;
            if (!key) return;
            if (state.sortKey === key) {
                state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                state.sortKey = key;
                state.sortDir = 'asc';
            }
            state.page = 1;
            render();
        });
    });

    render();
};

export const initTables = () => {
    document.querySelectorAll('[data-table]').forEach((table) => initTable(table));
};

document.addEventListener('DOMContentLoaded', initTables);
