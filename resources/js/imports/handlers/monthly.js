const buildPaymentKey = (monthName, item) => `${item?.row || ''}|${String(item?.name || '').trim().toLowerCase()}|${item?.phone || ''}|${String(monthName || '').toUpperCase()}|${Number(item?.amount || 0).toFixed(2)}`;

const MONTHS = {
    1: 'January', 2: 'February', 3: 'March', 4: 'April',
    5: 'May', 6: 'June', 7: 'July', 8: 'August',
    9: 'September', 10: 'October', 11: 'November', 12: 'December',
};

export const createMonthlyHandler = () => ({
    initState(state) {
        state.removedPayments = new Set();
        state.removedMonth = false;
        state.paymentItemsAll = [];
        state.paymentMonthName = '';
        state.monthSelectionReady = false;
    },
    resetState(state) {
        state.removedPayments?.clear();
        state.removedMonth = false;
        state.paymentItemsAll = [];
        state.paymentMonthName = '';
        state.monthSelectionReady = false;
    },
    onPreviewSuccess(payload, state) {
        state.previewData = payload;
        state.hasErrors = (payload?.errors || []).length > 0;
        state.paymentItemsAll = payload?.payments?.items || [];
        const monthName = payload?.payments?.month_name || payload?.month || '';
        state.paymentMonthName = String(monthName || '').toUpperCase();
    },
    buildPreviewFormData(formData, state, elements) {
        const year = elements.uploadYear?.value || elements.templateYear?.value || '';
        const month = elements.uploadMonth?.value || elements.templateMonth?.value || '';
        if (year) formData.append('year', year);
        if (month) formData.append('month', month);
    },
    buildFinalFormData(formData, state, elements) {
        const year = elements.uploadYear?.value || elements.templateYear?.value || '';
        const month = elements.uploadMonth?.value || elements.templateMonth?.value || '';
        if (year) formData.append('year', year);
        if (month) formData.append('month', month);
    },
    getErrorCount(state) {
        return (state.previewData?.errors || []).length;
    },
    getScrollableTabs() {
        return ['payments', 'errors'];
    },
    getTabMeta(state, tabs) {
        const data = state.previewData || {};
        const paymentsCount = data.paymentsInfo?.records_count || 0;
        const errorsCount = (data.errors || []).length;
        return tabs.map((tab) => {
            if (tab === 'summary') return { id: tab, label: 'Summary' };
            if (tab === 'payments') return { id: tab, label: 'Payments', count: paymentsCount };
            if (tab === 'errors') return { id: tab, label: 'Errors', count: errorsCount };
            return { id: tab, label: tab.charAt(0).toUpperCase() + tab.slice(1) };
        });
    },
    renderTab(tabId, state, helpers) {
        if (tabId === 'summary') return renderSummary(state.previewData || {}, helpers);
        if (tabId === 'payments') return renderPayments(state, helpers);
        if (tabId === 'errors') return renderErrors(state.previewData || {}, helpers);
        return '';
    },
    bindPreviewEvents({ elements, state, preview, helpers }) {
        elements.body?.addEventListener('click', (event) => {
            const target = event.target.closest('[data-action]');
            if (!target) return;
            const { action, key, month } = target.dataset;
            if (action === 'remove-payment' && key) state.removedPayments.add(key);
            if (action === 'remove-month') state.removedMonth = true;
            if (action === 'undo-month') state.removedMonth = false;
            if (action === 'undo-month-items') state.removedPayments.clear();
            const allItems = state.paymentItemsAll || [];
            const monthName = state.paymentMonthName || month || '';
            const removedAll = allItems.length > 0 && allItems.every((item) => state.removedPayments.has(buildPaymentKey(monthName, item)));
            if (state.removedMonth || removedAll) { helpers?.clearFile?.(); return; }
            preview.refresh();
            helpers?.updateImportButtonState?.();
        });
    },
    bindFormExtras({ elements, state, preview, helpers, checkMonthUrl }) {
        const modal = elements.modal;

        // ── Download template ─────────────────────────────────────────────
        elements.downloadTemplate?.addEventListener('click', () => {
            const year = elements.templateYear?.value || '';
            const month = elements.templateMonth?.value || '';
            const url = elements.downloadTemplate?.dataset.templateUrl;
            if (!url) return;
            window.location.href = `${url}?year=${encodeURIComponent(year)}&month=${encodeURIComponent(month)}`;
        });

        // ── Sync hidden inputs ────────────────────────────────────────────
        const syncHidden = () => {
            if (elements.uploadYear && elements.templateYear) elements.uploadYear.value = elements.templateYear.value;
            if (elements.uploadMonth && elements.templateMonth) elements.uploadMonth.value = elements.templateMonth.value;
        };

        // ── Central trigger ───────────────────────────────────────────────
        const triggerCheck = () => {
            syncHidden();
            const year = elements.templateYear?.value;
            const month = elements.templateMonth?.value;
            const ready = !!(year && month);
            state.monthSelectionReady = ready;
            _setFileInputEnabled(elements, ready);

            if (ready && checkMonthUrl) {
                _checkExistingPayments({ elements, helpers, checkMonthUrl, year, month });
            } else {
                helpers?.collapse?.();
                preview.showPlaceholder('Select a year and month to begin');
                helpers?.updateImportButtonState?.();
            }
        };

        elements.templateYear?.addEventListener('change', triggerCheck);
        elements.templateMonth?.addEventListener('change', triggerCheck);

        // Re-trigger check when file is cleared (returns to selection state)
        modal?.addEventListener('import:file-cleared', triggerCheck);

        // ── Init: run on next frame so expand() works after DOM is ready ──
        syncHidden();
        const initYear = elements.templateYear?.value;
        const initMonth = elements.templateMonth?.value;
        _setFileInputEnabled(elements, !!(initYear && initMonth));

        if (initYear && initMonth && checkMonthUrl) {
            requestAnimationFrame(() => triggerCheck());
        } else {
            preview.showPlaceholder('Select a year and month to begin');
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

async function _checkExistingPayments({ elements, helpers, checkMonthUrl, year, month }) {
    // Expand modal then show loading state
    helpers?.expand?.();
    _showCheckLoading(elements, 'Checking existing payments\u2026');

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        const body = new FormData();
        body.append('year', year);
        body.append('month', month);
        const response = await fetch(checkMonthUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body,
        });
        if (!response.ok) throw new Error('Network error');
        const data = await response.json();
        _renderMonthCheck(elements, data, year, month);
    } catch {
        _showCheckLoading(elements, 'Could not check existing payments.');
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

function _renderMonthCheck(elements, data, year, month) {
    if (!elements.previewContent || !elements.previewPlaceholder) return;
    const monthName = MONTHS[Number(month)] || month;
    const payments = data.payments || [];
    const total = payments.reduce((s, p) => s + Number(p.amount || 0), 0);
    const hasPayments = payments.length > 0;

    const rowsHtml = hasPayments
        ? payments.map((p) => `
            <div class="preview-month-item">
                <div class="name">${_esc(p.member_name || p.name || 'Member')}${p.phone ? `<span class="member-phone">${_esc(p.phone)}</span>` : ''}</div>
                <div class="amount">KSH ${Number(p.amount || 0).toLocaleString()}</div>
            </div>`).join('')
        : `<div class="preview-empty" style="padding:20px 0 12px">No payments recorded yet for this month.</div>`;

    const totalRow = hasPayments
        ? `<div class="preview-month-item preview-month-total-row">
               <div class="name"><strong>Total (${payments.length} record${payments.length !== 1 ? 's' : ''})</strong></div>
               <div class="amount"><strong>KSH ${total.toLocaleString()}</strong></div>
           </div>`
        : '';

    if (elements.tabs) elements.tabs.innerHTML = '';
    elements.body.className = 'preview-tab-body';
    elements.body.innerHTML = `
        <div class="preview-glass preview-month-check-panel">
            <div class="preview-month-check-header">
                <div>
                    <div class="preview-month-title">${_esc(monthName.toUpperCase())} ${_esc(String(year))}</div>
                </div>
                <div>
                    ${hasPayments
                        ? `<span class="member-pill member-pill-existing">${payments.length} payment${payments.length !== 1 ? 's' : ''} recorded</span>`
                        : `<span class="member-pill member-pill-new">No payments yet</span>`}
                </div>
            </div>
            <div class="preview-month-items" style="max-height:240px;overflow-y:auto">${rowsHtml}</div>
            ${totalRow}
            <div class="preview-month-check-hint">Upload a file below to import payments for this month.</div>
        </div>`;

    elements.previewPlaceholder.style.display = 'none';
    elements.previewContent.style.display = 'block';
}

const _esc = (v) => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

// ── Tab renderers ─────────────────────────────────────────────────────────────

const renderSummary = (data, { escapeHtml, formatKES }) => {
    const payments = data.paymentsInfo || {};
    const members = data.membersInfo || {};
    return `
    <div class="preview-glass preview-overview">
        <div class="preview-summary-grid">
            <div class="preview-summary-item"><div class="preview-summary-label">Year</div><div class="preview-summary-value-content"><span class="value-text-bold">${escapeHtml(data.year || '-')}</span></div></div>
            <div class="preview-summary-item"><div class="preview-summary-label">Month</div><div class="preview-summary-value-content"><span class="value-text-bold">${escapeHtml(data.month || '-')}</span></div></div>
            <div class="preview-summary-item"><div class="preview-summary-label">Payments</div><div class="preview-summary-value-content"><span class="value-text-bold">${payments.records_count || 0}</span></div></div>
            <div class="preview-summary-item"><div class="preview-summary-label">Payments Total</div><div class="preview-summary-value-content"><span class="value-text-bold">${formatKES(payments.total_amount || 0)}</span></div></div>
            <div class="preview-summary-item"><div class="preview-summary-label">Members (Existing)</div><div class="preview-summary-value-content"><span class="value-text-bold">${members.existing_count || 0}</span></div></div>
            <div class="preview-summary-item"><div class="preview-summary-label">Members (New)</div><div class="preview-summary-value-content"><span class="value-text-bold">${members.new_count || 0}</span></div></div>
        </div>
    </div>`;
};

const renderPayments = (state, { escapeHtml, formatKES, icons }) => {
    const data = state.previewData || {};
    const payments = data.payments || {};
    const totals = payments.totals || data.paymentsInfo || {};
    const monthName = String(payments.month_name || data.month || '').toUpperCase();
    const allItems = payments.items || [];
    const items = allItems.filter((item) => !state.removedPayments.has(buildPaymentKey(monthName, item)));
    if (!allItems.length) return '<div class="preview-empty">No payments detected for this month.</div>';
    const emptyRemoved = !state.removedMonth && allItems.length > 0 && items.length === 0;
    if (state.removedMonth) return `<div class="preview-card-grid preview-tab-scroll"><div class="preview-glass preview-month-card preview-month-card-deleted"><div><div class="preview-month-title">${escapeHtml(monthName)}</div><div class="preview-month-meta">This month has been removed from preview.</div></div><button type="button" class="preview-undo-btn" data-action="undo-month">Undo Delete</button></div></div>`;
    if (emptyRemoved) return `<div class="preview-card-grid preview-tab-scroll"><div class="preview-glass preview-month-card preview-month-card-deleted"><div><div class="preview-month-title">${escapeHtml(monthName)}</div><div class="preview-month-meta">All items deleted.</div></div><button type="button" class="preview-undo-btn" data-action="undo-month-items">Undo Delete</button></div></div>`;
    return `
    <div class="preview-card-grid preview-tab-scroll">
        <div class="preview-glass preview-month-card">
            <div class="preview-month-head">
                <div>
                    <div class="preview-month-title">${escapeHtml(monthName)}</div>
                    <div class="preview-month-meta">Payments: <strong>${totals.records_count || 0}</strong></div>
                    <div class="preview-month-meta">Total: <strong>${formatKES(totals.total_amount || 0)}</strong></div>
                </div>
                <button type="button" class="preview-month-delete-btn" data-action="remove-month">${icons.trash}<span>Delete X</span></button>
            </div>
            <div class="preview-month-items">
                ${items.map((item) => `
                    <div class="preview-month-item">
                        <div class="name">${escapeHtml(item.name || 'Member')}${item.phone ? `<span class="member-phone">${escapeHtml(item.phone)}</span>` : ''}</div>
                        <div class="amount">${formatKES(item.amount || 0)}</div>
                        <button type="button" class="preview-remove-btn" data-action="remove-payment" data-key="${escapeHtml(buildPaymentKey(monthName, item))}">${icons.trash}<span>Delete X</span></button>
                    </div>`).join('')}
            </div>
        </div>
    </div>`;
};

const renderErrors = (data, { escapeHtml }) => {
    const errors = data.errors || [];
    if (!errors.length) return '<div class="preview-empty">No errors found.</div>';
    return `<div class="preview-error-grid preview-tab-scroll">${errors.map((message) => `<div class="preview-glass preview-error-card"><div class="preview-error-head">Import Validation</div><div class="preview-error-body">${escapeHtml(message)}</div></div>`).join('')}</div>`;
};