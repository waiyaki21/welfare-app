export const createExpenditureHandler = () => ({
    initState(state) {
        state.yearSelectionReady = false;
    },
    resetState(state) {
        state.yearSelectionReady = false;
    },
    onPreviewSuccess(payload, state) {
        state.previewData = payload;
        state.hasErrors = (payload?.errors || []).length > 0;
    },
    buildPreviewFormData(formData, state, elements) {
        const year = elements.yearInput?.value || elements.templateYear?.value || '';
        if (year) formData.append('year', year);
    },
    buildFinalFormData(formData, state, elements) {
        const year = elements.yearInput?.value || elements.templateYear?.value || '';
        if (year) formData.append('year', year);
    },
    getErrorCount(state) {
        return (state.previewData?.errors || []).length;
    },
    getScrollableTabs() {
        return ['expenses', 'errors'];
    },
    getTabMeta(state, tabs) {
        const data = state.previewData || {};
        const expensesCount = data.expenditures?.totals?.records_count || 0;
        const errorsCount = (data.errors || []).length;
        const narrationCount = Object.keys(data.expenditures?.narrations || {}).length;
        return tabs.map((tab) => {
            if (tab === 'summary') return { id: tab, label: 'Summary' };
            if (tab === 'expenses') return { id: tab, label: 'Expenses', count: narrationCount || expensesCount };
            if (tab === 'errors') return { id: tab, label: 'Errors', count: errorsCount };
            return { id: tab, label: tab.charAt(0).toUpperCase() + tab.slice(1) };
        });
    },
    renderTab(tabId, state, helpers) {
        if (tabId === 'summary') return renderSummary(state.previewData || {}, helpers);
        if (tabId === 'expenses') return renderExpenses(state.previewData || {}, helpers);
        if (tabId === 'errors') return renderErrors(state.previewData || {}, helpers);
        return '';
    },
    bindFormExtras({ elements, state, preview, helpers, checkYearUrl }) {
        const modal = elements.modal;

        // ── Download template ─────────────────────────────────────────────
        elements.downloadTemplate?.addEventListener('click', () => {
            const year = elements.templateYear?.value || elements.yearInput?.value || '';
            const baseUrl = elements.downloadTemplate?.dataset.templateUrl || '';
            if (!baseUrl || !year) return;
            window.location.href = `${baseUrl}/${encodeURIComponent(year)}`;
        });

        // ── Central trigger ───────────────────────────────────────────────
        const triggerCheck = () => {
            const year = elements.yearInput?.value || elements.templateYear?.value || '';
            const ready = !!year;
            state.yearSelectionReady = ready;
            _setFileInputEnabled(elements, ready);

            if (ready && checkYearUrl) {
                _checkExistingExpenditures({ elements, helpers, checkYearUrl, year });
            } else {
                helpers?.collapse?.();
                preview.showPlaceholder('Select a financial year to begin');
                helpers?.updateImportButtonState?.();
            }
        };

        elements.yearInput?.addEventListener('change', triggerCheck);
        elements.templateYear?.addEventListener('change', triggerCheck);

        // Re-trigger after file cleared
        modal?.addEventListener('import:file-cleared', triggerCheck);

        // ── Init ──────────────────────────────────────────────────────────
        const initYear = elements.yearInput?.value || elements.templateYear?.value || '';
        _setFileInputEnabled(elements, !!initYear);

        if (initYear && checkYearUrl) {
            requestAnimationFrame(() => triggerCheck());
        } else {
            preview.showPlaceholder('Select a financial year to begin');
        }
    },
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function _setFileInputEnabled(elements, enabled) {
    if (elements.dropZone) {
        elements.dropZone.style.pointerEvents = enabled ? '' : 'none';
        elements.dropZone.style.opacity       = enabled ? '' : '0.45';
        elements.dropZone.style.cursor        = enabled ? '' : 'not-allowed';
    }
    if (elements.fileInput) elements.fileInput.disabled = !enabled;
    if (elements.importButton && !enabled) elements.importButton.disabled = true;
}

async function _checkExistingExpenditures({ elements, helpers, checkYearUrl, year }) {
    helpers?.expand?.();
    _showCheckLoading(elements, 'Checking existing expenditures\u2026');

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        const body = new FormData();
        body.append('year', year);
        const response = await fetch(checkYearUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body,
        });
        if (!response.ok) throw new Error('Network error');
        const data = await response.json();
        _renderYearCheck(elements, data, year);
    } catch {
        _showCheckLoading(elements, 'Could not check existing expenditures.');
    }
}

function _showCheckLoading(elements, message) {
    if (!elements.previewContent || !elements.previewPlaceholder) return;
    elements.previewPlaceholder.style.display = 'flex';
    elements.previewContent.style.display = 'none';
    elements.previewPlaceholder.innerHTML = `
        <div style="text-align:center">
            <div class="preview-spinner" style="margin:0 auto 10px"></div>
            <div style="font-weight:600;margin-bottom:4px">Please wait</div>
            <div style="font-size:.82rem;opacity:.7">${_esc(message)}</div>
        </div>`;
}

function _renderYearCheck(elements, data, year) {
    if (!elements.previewContent || !elements.previewPlaceholder) return;
    const expenditures = data.expenditures || [];
    const total = expenditures.reduce((s, e) => s + Number(e.amount || 0), 0);
    const hasExp = expenditures.length > 0;

    const rowsHtml = hasExp
        ? expenditures.map((e) => `
            <div class="preview-month-item">
                <div class="name">${_esc(e.narration || e.name || 'Expenditure')}</div>
                <div class="amount">KSH ${Number(e.amount || 0).toLocaleString()}</div>
            </div>`).join('')
        : `<div class="preview-empty" style="padding:20px 0 12px">No expenditures recorded yet for this year.</div>`;

    const totalRow = hasExp
        ? `<div class="preview-month-item preview-month-total-row">
               <div class="name"><strong>Total (${expenditures.length} record${expenditures.length !== 1 ? 's' : ''})</strong></div>
               <div class="amount"><strong>KSH ${total.toLocaleString()}</strong></div>
           </div>`
        : '';

    if (elements.tabs) elements.tabs.innerHTML = '';
    elements.body.className = 'preview-tab-body';
    elements.body.innerHTML = `
        <div class="preview-glass preview-month-check-panel">
            <div class="preview-month-check-header">
                <div>
                    <div class="preview-month-title">YEAR ${_esc(String(year))}</div>
                </div>
                <div>
                    ${hasExp
                        ? `<span class="member-pill member-pill-existing">${expenditures.length} expenditure${expenditures.length !== 1 ? 's' : ''} recorded</span>`
                        : `<span class="member-pill member-pill-new">No expenditures yet</span>`}
                </div>
            </div>
            <div class="preview-month-items" style="max-height:240px;overflow-y:auto">${rowsHtml}</div>
            ${totalRow}
            <div class="preview-month-check-hint">Upload a file below to import expenditures for this year.</div>
        </div>`;

    elements.previewPlaceholder.style.display = 'none';
    elements.previewContent.style.display = 'block';
}

const _esc = (v) => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

// ── Tab renderers ─────────────────────────────────────────────────────────────

const renderSummary = (data, { escapeHtml, formatKES }) => {
    const overview = data.overview || {};
    return `
    <div class="preview-glass preview-overview">
        <div class="preview-summary-grid">
            <div class="preview-summary-item"><div class="preview-summary-label">Year</div><div class="preview-summary-value-content"><span class="value-text-bold">${escapeHtml(overview.year || '-')}</span></div></div>
            <div class="preview-summary-item"><div class="preview-summary-label">Total Records</div><div class="preview-summary-value-content"><span class="value-text-bold">${overview.total_records || 0}</span></div></div>
            <div class="preview-summary-item"><div class="preview-summary-label">Total Amount</div><div class="preview-summary-value-content"><span class="value-text-bold">${formatKES(overview.total_amount || 0)}</span></div></div>
            <div class="preview-summary-item"><div class="preview-summary-label">Narrations Detected</div><div class="preview-summary-value-content"><span class="value-text-bold">${overview.narrations_detected || 0}</span></div></div>
        </div>
    </div>`;
};

const renderExpenses = (data, { escapeHtml, formatKES }) => {
    const entries = Object.entries((data.expenditures?.narrations || {})).filter(([, info]) => (info.records_count || 0) > 0);
    if (!entries.length) return '<div class="preview-empty">No expenditures detected.</div>';
    return `<div class="preview-card-grid preview-tab-scroll">${entries.map(([name, info]) => `
        <div class="preview-glass preview-month-card">
            <div class="preview-month-title">${escapeHtml(name)}</div>
            <div class="preview-month-meta">Entries: <strong>${info.records_count || 0}</strong></div>
            <div class="preview-month-meta">Total: <strong>${formatKES(info.total_amount || 0)}</strong></div>
            <div class="preview-month-items">${(info.items || []).map((item) => `
                <div class="preview-month-item">
                    <span class="name">${escapeHtml(item.name || 'Expenditure')}</span>
                    <span class="amount">${formatKES(item.amount || 0)}</span>
                </div>`).join('')}
            </div>
        </div>`).join('')}</div>`;
};

const renderErrors = (data, { escapeHtml }) => {
    const errors = data.errors || [];
    if (!errors.length) return '<div class="preview-empty">No errors found.</div>';
    return `<div class="preview-error-grid preview-tab-scroll">${errors.map((message) => `<div class="preview-glass preview-error-card"><div class="preview-error-head">Import Validation</div><div class="preview-error-body">${escapeHtml(message)}</div></div>`).join('')}</div>`;
};