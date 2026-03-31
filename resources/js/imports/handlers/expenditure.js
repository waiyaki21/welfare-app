export const createExpenditureHandler = () => ({
    initState() {},
    resetState() {},
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
    bindFormExtras({ elements }) {
        elements.downloadTemplate?.addEventListener('click', () => {
            const year = elements.templateYear?.value || elements.yearInput?.value || '';
            const baseUrl = elements.downloadTemplate?.dataset.templateUrl || '';
            if (!baseUrl || !year) return;
            window.location.href = `${baseUrl}/${encodeURIComponent(year)}`;
        });
    }
});

const renderSummary = (data, { escapeHtml, formatKES }) => {
    const overview = data.overview || {};

    return `
    <div class="preview-glass preview-overview">
        <div class="preview-summary-grid">
            <div class="preview-summary-item">
                <div class="preview-summary-label">Year</div>
                <div class="preview-summary-value-content"><span class="value-text-bold">${escapeHtml(overview.year || '-') }</span></div>
            </div>
            <div class="preview-summary-item">
                <div class="preview-summary-label">Total Records</div>
                <div class="preview-summary-value-content"><span class="value-text-bold">${overview.total_records || 0}</span></div>
            </div>
            <div class="preview-summary-item">
                <div class="preview-summary-label">Total Amount</div>
                <div class="preview-summary-value-content"><span class="value-text-bold">${formatKES(overview.total_amount || 0)}</span></div>
            </div>
            <div class="preview-summary-item">
                <div class="preview-summary-label">Narrations Detected</div>
                <div class="preview-summary-value-content"><span class="value-text-bold">${overview.narrations_detected || 0}</span></div>
            </div>
        </div>
    </div>`;
};

const renderExpenses = (data, { escapeHtml, formatKES }) => {
    const entries = Object.entries((data.expenditures?.narrations || {})).filter(([, info]) => (info.records_count || 0) > 0);
    if (!entries.length) return '<div class="preview-empty">No expenditures detected.</div>';

    return `<div class="preview-card-grid preview-tab-scroll">${entries.map(([monthName, info]) => `
        <div class="preview-glass preview-month-card">
            <div class="preview-month-title">${escapeHtml(monthName)}</div>
            <div class="preview-month-meta">Entries: <strong>${info.records_count || 0}</strong></div>
            <div class="preview-month-meta">Total: <strong>${formatKES(info.total_amount || 0)}</strong></div>
            <div class="preview-month-items">
                ${(info.items || []).map((item) => `
                    <div class="preview-month-item">
                        <span class="name">${escapeHtml(item.name || 'Expenditure')}</span>
                        <span class="amount">${formatKES(item.amount || 0)}</span>
                    </div>
                `).join('')}
            </div>
        </div>
    `).join('')}</div>`;
};

const renderErrors = (data, { escapeHtml }) => {
    const errors = data.errors || [];
    if (!errors.length) return '<div class="preview-empty">No errors found.</div>';
    return `<div class="preview-error-grid preview-tab-scroll">${errors.map((message) => `
        <div class="preview-glass preview-error-card">
            <div class="preview-error-head">Import Validation</div>
            <div class="preview-error-body">${escapeHtml(message)}</div>
        </div>
    `).join('')}</div>`;
};
