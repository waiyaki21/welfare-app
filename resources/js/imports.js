(() => {
    const config = window.importsConfig || {};
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const icons = {
        trash: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="m19 6-1 14H6L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path></svg>',
        spinner: '<svg class="preview-spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>',
        undo: '<svg width="12" height="12" viewBox="0 0 25 25" fill="none">< path d="M5.88468 17C7.32466 19.1128 9.75033 20.5 12.5 20.5C16.9183 20.5 20.5 16.9183 20.5 12.5C20.5 8.08172 16.9183 4.5 12.5 4.5C8.08172 4.5 4.5 8.08172 4.5 12.5V13.5" stroke="#121923" stroke- width="1.2" /><path d="M7 11L4.5 13.5L2 11" stroke="#121923" stroke-width="1.2" /></svg >',
    };

    const qs = (id) => document.getElementById(id);
    const formatKES = (value) => `KSH ${Number(value || 0).toLocaleString()}`;
    const escapeHtml = (value) => String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    const createTabButton = (id, title, count) => `<button type="button" class="year-tab-btn" data-id="${id}"><span>${title}</span>${typeof count === 'number' ? `<span class="year-tab-badge">${count}</span>` : ''}</button>`;
    const postForm = async (url, formData) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
            body: formData,
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Request failed');
        return data;
    };

    function bindModal(rootId, openButtonId, closeButtonIds) {
        const root = qs(rootId);
        if (!root) return;
        qs(openButtonId)?.addEventListener('click', () => root.classList.add('open'));
        closeButtonIds.forEach((id) => qs(id)?.addEventListener('click', () => root.classList.remove('open')));
        root.addEventListener('click', (event) => {
            if (event.target === root) root.classList.remove('open');
        });
    }

    function attachDropZone(dropZone, fileInput, onFile) {
        if (!dropZone || !fileInput) return;
        dropZone.addEventListener('click', () => fileInput.click());
        dropZone.addEventListener('dragover', (event) => {
            event.preventDefault();
            dropZone.classList.add('import-drop-zone-active');
        });
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('import-drop-zone-active'));
        dropZone.addEventListener('drop', (event) => {
            event.preventDefault();
            dropZone.classList.remove('import-drop-zone-active');
            const file = event.dataTransfer?.files?.[0];
            if (!file) return;
            const dt = new DataTransfer();
            dt.items.add(file);
            fileInput.files = dt.files;
            onFile(file);
        });
        fileInput.addEventListener('change', () => fileInput.files?.[0] && onFile(fileInput.files[0]));
    }

    function yearController(options) {
        const state = {
            previewReady: false,
            previewData: null,
            memberFilter: 'all', // all | existing | new | errors
            activeTab: 'overview',
            removedMembers: new Set(),
            removedPayments: new Set(),
            removedExpenses: new Set(),
            removedPaymentMonths: new Map(),
            removedExpenseMonths: new Map(),
            paymentMonthItems: new Map(),
            expenseMonthItems: new Map(),
        };
        const el = {
            modal: qs('importModal'),
            dialog: qs('yearImportDialog'),
            form: qs('import-form'),
            fileInput: qs('file-input'),
            fileInfo: qs('file-info'),
            fileName: qs('file-name'),
            dropZone: qs('drop-zone'),
            dropLabel: qs('drop-label'),
            importButton: qs('import-btn'),
            previewSection: qs('year-preview-section'),
            previewPlaceholder: qs('year-preview-placeholder'),
            previewContent: qs('year-preview-content'),
            tabs: qs('year-tabs'),
            body: qs('year-tab-body'),
            clearButton: qs('clearYearImportFile'),
        };

        const memberKey = (row) => `${row?.row || ''}|${String(row?.name || '').trim().toLowerCase()}|${row?.phone || ''}`;
        const paymentKey = (monthName, item) => `${item?.row || ''}|${String(item?.name || '').trim().toLowerCase()}|${item?.phone || ''}|${String(monthName || '').toUpperCase()}|${Number(item?.amount || 0).toFixed(2)}`;
        const expenseKey = (item) => `${item?.row || ''}|${String(item?.category || '').trim().toLowerCase()}|${item?.month || ''}|${Number(item?.amount || 0).toFixed(2)}`;

        const reset = () => {
            state.previewReady = false;
            state.previewData = null;
            state.activeTab = 'overview';
            state.removedMembers.clear();
            state.removedPayments.clear();
            state.removedExpenses.clear();
            state.removedPaymentMonths.clear();
            state.removedExpenseMonths.clear();
            state.paymentMonthItems.clear();
            state.expenseMonthItems.clear();
        };

        const showPlaceholder = (text = 'No sheet uploaded') => {
            el.previewContent.style.display = 'none';
            el.previewPlaceholder.style.display = 'flex';
            const loading = text.toLowerCase().includes('generating preview');
            el.previewPlaceholder.innerHTML = `<div>${loading ? '<div class="preview-spinner"></div>' : ''}<div style="font-weight:700;margin-bottom:6px;">${escapeHtml(text === 'No sheet uploaded' ? 'No sheet uploaded' : 'Preparing preview')}</div><div style="font-size:.82rem;">${escapeHtml(text)}</div></div>`;
        };

        const showPreviewError = () => {
            el.previewContent.style.display = 'none';
            el.previewPlaceholder.style.display = 'flex';
            el.previewPlaceholder.innerHTML = '<div class="preview-error-state"><div style="font-weight:700;margin-bottom:6px;">Import Preview Failed</div><div>The spreadsheet could not be read</div></div>';
        };

        const expand = () => {
            el.dialog.style.maxWidth = '1080px';
            el.previewSection.style.display = 'block';
            requestAnimationFrame(() => { el.previewSection.style.opacity = '1'; });
        };
        const collapse = () => {
            el.dialog.style.maxWidth = '580px';
            el.previewSection.style.opacity = '0';
            window.setTimeout(() => { el.previewSection.style.display = 'none'; }, 220);
        };

        const renderErrors = (data) => {
            const members = data.members || data.members_info || {};
            const memberErrors = (members.error_members || []).flatMap((entry) => (entry.errors || []).map((message) => ({
                title: `${entry.name || 'Unknown member'}${entry.phone ? ` (${entry.phone})` : ''}`,
                message,
            })));
            const genericErrors = (data.errors || []).map((message) => ({ title: 'General Validation', message }));
            const errors = [...memberErrors, ...genericErrors];
            if (!errors.length) return '<div class="preview-empty">No errors found.</div>';
            return `<div class="preview-error-grid preview-tab-scroll">${errors.map((error) => `<div class="preview-glass preview-error-card"><div class="preview-error-head">${escapeHtml(error.title)}</div><div class="preview-error-body">${escapeHtml(error.message)}</div></div>`).join('')}</div>`;
        };

        const renderMembers = (data) => {
            const scrollTop = el.body.scrollTop; // save scroll

            const members = data.members || data.members_info || {};
            const allMembers = members.members || [];

            const rows = allMembers.map((row) => {
                const key = memberKey(row);
                const isDeleted = state.removedMembers.has(key);
                const hasErrors = (row.errors || []).length > 0;

                const badgeText = hasErrors ? 'Errors' : row.status === 'existing' ? 'Existing' : 'New';
                const badgeClass = hasErrors ? 'badge-error' : row.status === 'existing' ? 'badge-existing' : 'badge-new';

                const deletedBadgeClass = isDeleted ? 'badge-deleted' : '';

                return {
                    ...row,
                    key,
                    isDeleted,
                    badgeText,
                    badgeClass,
                    deletedBadgeClass
                };
            });

            // counts
            const counts = {
                deleted: rows.filter(r => r.isDeleted).length,
                total: rows.length,
                existing: rows.filter(r => r.status === 'existing' && !r.isDeleted).length,
                new: rows.filter(r => r.status === 'new' && !r.isDeleted).length,
                errors: rows.filter(r => (r.errors || []).length > 0 && !r.isDeleted).length,
            };

            el.body.scrollTop = scrollTop; // restore scroll

            return `<div class="preview-glass preview-members">
        <div class="preview-members-summary flex justify-between items-center">
            <div class="filters-left flex gap-2">
                <button class="filter-chip ${state.memberFilter === 'existing' ? 'active' : ''}" data-filter="existing">
                    Existing: <strong>${counts.existing}</strong>
                </button>
                <button class="filter-chip ${state.memberFilter === 'new' ? 'active' : ''}" data-filter="new">
                    New: <strong>${counts.new}</strong>
                </button>
                <button class="filter-chip ${state.memberFilter === 'errors' ? 'active' : ''}" data-filter="errors">
                    Errors: <strong>${counts.errors}</strong>
                </button>
                ${counts.deleted > 0 ? `<button class="filter-chip ${state.memberFilter === 'deleted' ? 'active' : ''}" data-filter="deleted">
                    Deleted: <strong>${counts.deleted}</strong>
                </button>` : ''}
            </div>
            <div class="filters-right">
                <button class="filter-chip ${state.memberFilter === 'all' ? 'active' : ''}" data-filter="all">
                    View All: <strong>${counts.total}</strong>
                </button>
            </div>
        </div>

        <div class="preview-table-wrap">
            <table class="preview-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th class="num">C/F</th>
                        <th class="num" title="Total Contributions">T.C</th>
                        <th class="num" title="Total Welfares">T.W</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows
                    .filter(row => {
                        if (state.memberFilter === 'existing') return row.status === 'existing' && !row.isDeleted;
                        if (state.memberFilter === 'new') return row.status === 'new' && !row.isDeleted;
                        if (state.memberFilter === 'errors') return (row.errors || []).length > 0 && !row.isDeleted;
                        if (state.memberFilter === 'deleted') return row.isDeleted;
                        return true; // all
                    })
                    .map((row, index) => `
                        <tr class="${row.isDeleted ? 'preview-row-deleted' : (row.errors || []).length ? 'preview-row-error' : ''}">
                            <td>${index + 1}</td>
                            <td>
                                <div class="preview-member-row">
                                    <div class="member-info">
                                        <div class="member-name" title="${escapeHtml(row.name || '-')}">
                                            ${escapeHtml(row.name || '-')}
                                        </div>
                                        <div class="member-phone">
                                            ${escapeHtml(row.phone || 'No phone')}
                                            <span class="member-badge ${row.badgeClass}">${row.badgeText}</span>
                                            ${row.isDeleted ? `<span class="member-badge badge-deleted">Deleted</span>` : ''}
                                        </div>
                                    </div>
                                    <button type="button" class="preview-remove-btn inline" 
                                        data-action="${row.isDeleted ? 'undo-member' : 'remove-member'}" 
                                        data-key="${escapeHtml(row.key)}">
                                        ${row.isDeleted ? icons.undo : icons.trash}
                                    </button>
                                </div>
                                ${(row.errors || []).length ? `<div class="member-inline-error">${escapeHtml((row.errors || []).join(' '))}</div>` : ''}
                            </td>
                            <td class="num">${Number(row.contributions_carried_forward || 0).toLocaleString()}</td>
                            <td class="num">${Number(row.total_contributions || 0).toLocaleString()}</td>
                            <td class="num">${Number(row.total_welfare || 0).toLocaleString()}</td>
                        </tr>`).join('')}
                </tbody>
            </table>
        </div>
    </div>`;
        };

        const renderOverview = (data) => {
            const o = data.overview || {};
            const rows = [['Total Members', o.total_members || 0], ['Total Contributions', formatKES(o.total_contributions || 0)], ['Total Welfare', formatKES(o.total_welfare || 0)], ['Total Expenses', formatKES(o.total_expenses || 0)], ['Total Payments', formatKES(o.total_payments || 0)]];
            return `<div class="preview-glass preview-overview"><div class="preview-summary-grid">${rows.map(([label, value]) => `<div class="preview-summary-item"><div class="preview-summary-label">${escapeHtml(label)}</div><div class="preview-summary-value">${escapeHtml(value)}</div></div>`).join('')}</div></div>`;
        };

        const renderMonthCards = (type, months) => {
            const entries = Object.entries(months || {}).map(([monthName, info]) => {
                const items = (info.items || []).filter((item) => type === 'payment' ? !state.removedPayments.has(paymentKey(monthName, item)) : !state.removedExpenses.has(expenseKey(item)));
                if (type === 'payment') state.paymentMonthItems.set(monthName, items);
                if (type === 'expense') state.expenseMonthItems.set(monthName, items);
                return [monthName, { items, deleted: type === 'payment' ? state.removedPaymentMonths.has(monthName) : state.removedExpenseMonths.has(monthName), total: items.reduce((sum, item) => sum + Number(item.amount || 0), 0) }];
            }).filter(([, info]) => info.deleted || info.items.length > 0);
            if (!entries.length) return `<div class="preview-empty">No ${type === 'payment' ? 'monthly payments' : 'expenses'} detected.</div>`;
            return `<div class="preview-card-grid preview-tab-scroll">${entries.map(([monthName, info]) => info.deleted ? `<div class="preview-glass preview-month-card preview-month-card-deleted"><div><div class="preview-month-title">${escapeHtml(monthName.toUpperCase())}</div><div class="preview-month-meta">This month has been removed from preview.</div></div><button type="button" class="preview-undo-btn" data-action="undo-month" data-type="${type}" data-month="${escapeHtml(monthName)}">Undo Delete</button></div>` : `<div class="preview-glass preview-month-card"><div class="preview-month-head"><div><div class="preview-month-title">${escapeHtml(monthName.toUpperCase())}</div><div class="preview-month-meta">${type === 'payment' ? 'Payments' : 'Expenses'}: <strong>${info.items.length}</strong></div><div class="preview-month-meta">Total: <strong>${formatKES(info.total)}</strong></div></div><button type="button" class="preview-month-delete-btn" data-action="remove-month" data-type="${type}" data-month="${escapeHtml(monthName)}">${icons.trash}<span>Delete</span></button></div><div class="preview-month-items">${info.items.map((item) => ` <div class="preview-month-item"><div class="name"> ${escapeHtml(type === 'payment' ? (item.name || 'Unknown') : (item.category || 'Expense'))} </div><div class="amount"> ${formatKES(item.amount || 0)} </div><button type="button" class="preview-remove-btn" data-action="remove-${type}" data-key="${escapeHtml(type === 'payment' ? paymentKey(monthName, item) : expenseKey(item))}"> ${icons.trash} </button></div>`).join('')}</div></div>`).join('')}</div>`;
        };

        const activate = (tabId) => {
            state.activeTab = tabId;
            el.body.className = 'preview-tab-body';
            if (tabId === 'payments' || tabId === 'expenses' || tabId === 'errors') el.body.classList.add('preview-tab-body-scrollable');
            if (tabId === 'overview') el.body.innerHTML = renderOverview(state.previewData || {});
            if (tabId === 'members') el.body.innerHTML = renderMembers(state.previewData || {});
            if (tabId === 'payments') el.body.innerHTML = renderMonthCards('payment', ((state.previewData || {}).payments || {}).months || {});
            if (tabId === 'expenses') el.body.innerHTML = renderMonthCards('expense', ((state.previewData || {}).expenses || {}).months || {});
            if (tabId === 'errors') el.body.innerHTML = renderErrors(state.previewData || {});
            el.tabs.querySelectorAll('.year-tab-btn').forEach((button) => button.classList.toggle('active', button.dataset.id === tabId));
        };

        const renderPreview = (payload, resetRemovals = true) => {
            if (resetRemovals) {
                reset();
                state.previewData = payload;
            } else {
                state.previewData = payload;
            }
            const members = payload.members || payload.members_info || {};
            const payments = payload.payments || { months: {} };
            const expenses = payload.expenses || { months: {} };
            const memberCount = (members.members || []).filter((row) => !state.removedMembers.has(memberKey(row))).length;
            const paymentMonths = Object.entries(payments.months || {}).filter(([monthName, month]) => state.removedPaymentMonths.has(monthName) || (month.items || []).some((item) => !state.removedPayments.has(paymentKey(monthName, item)))).length;
            const expenseMonths = Object.entries(expenses.months || {}).filter(([monthName, month]) => state.removedExpenseMonths.has(monthName) || (month.items || []).some((item) => !state.removedExpenses.has(expenseKey(item)))).length;
            const errorCount = (payload.errors || []).length + (members.error_members || []).length;
            el.previewPlaceholder.style.display = 'none';
            el.previewContent.style.display = 'block';
            el.tabs.innerHTML = [createTabButton('overview', 'Overview'), createTabButton('members', 'Members', memberCount), createTabButton('payments', 'Payments', paymentMonths), createTabButton('expenses', 'Expenses', expenseMonths), createTabButton('errors', 'Errors', errorCount)].join('');
            el.tabs.querySelectorAll('.year-tab-btn').forEach((button) => button.addEventListener('click', () => activate(button.dataset.id)));
            activate(state.activeTab || 'overview');
        };

        const previewFile = async () => {
            const file = el.fileInput?.files?.[0];
            if (!file) return;
            const formData = new FormData();
            formData.append('spreadsheet', file);
            expand();
            showPlaceholder('Generating preview... please wait');
            el.importButton.disabled = true;
            try {
                const payload = await postForm(options.previewUrl, formData);
                renderPreview(payload);
                state.previewReady = true;
                el.importButton.disabled = false;
                el.importButton.textContent = 'Import';
            } catch {
                state.previewReady = false;
                showPreviewError();
            }
        };

        attachDropZone(el.dropZone, el.fileInput, (file) => {
            el.dropLabel.textContent = 'File ready';
            el.fileInfo.style.display = 'flex';
            el.fileName.textContent = file.name;
            previewFile();
        });
        el.clearButton?.addEventListener('click', () => {
            el.fileInput.value = '';
            el.dropLabel.textContent = 'Drop your .xlsx file here';
            el.fileInfo.style.display = 'none';
            showPlaceholder();
            collapse();
            reset();
            el.importButton.disabled = true;
            el.importButton.textContent = 'Import';
        });
        el.form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!state.previewReady) return;
            const file = el.fileInput?.files?.[0];
            if (!file) return;
            const formData = new FormData();
            formData.append('spreadsheet', file);
            formData.append('removed_members', JSON.stringify(Array.from(state.removedMembers)));
            formData.append('removed_payments', JSON.stringify(Array.from(state.removedPayments)));
            formData.append('removed_expenses', JSON.stringify(Array.from(state.removedExpenses)));
            try {
                el.importButton.disabled = true;
                el.importButton.innerHTML = `${icons.spinner}<span>Importing...</span>`;
                await postForm(options.finalUrl, formData);
                window.location.reload();
            } catch (error) {
                window.alert(error.message || 'Final import failed.');
                el.importButton.disabled = false;
                el.importButton.textContent = 'Import';
            }
        });
        el.body?.addEventListener('click', (event) => {
            const target = event.target.closest('[data-action], [data-filter]');
            if (!target) return;

            const { action, key, type, month } = target.dataset;

            // === Normal remove / undo actions ===
            if (action === 'undo-member' && key) state.removedMembers.delete(key);
            if (action === 'remove-member' && key) state.removedMembers.add(key);
            if (action === 'remove-payment' && key) state.removedPayments.add(key);
            if (action === 'remove-expense' && key) state.removedExpenses.add(key);
            if (action === 'remove-month' && type && month) {
                const items = type === 'payment' ? (state.paymentMonthItems.get(month) || []) : (state.expenseMonthItems.get(month) || []);
                const keys = items.map((item) => type === 'payment' ? paymentKey(month, item) : expenseKey(item));
                if (type === 'payment') {
                    keys.forEach((v) => state.removedPayments.add(v));
                    state.removedPaymentMonths.set(month, keys);
                } else {
                    keys.forEach((v) => state.removedExpenses.add(v));
                    state.removedExpenseMonths.set(month, keys);
                }
            }
            if (action === 'undo-month' && type && month) {
                const keys = type === 'payment' ? (state.removedPaymentMonths.get(month) || []) : (state.removedExpenseMonths.get(month) || []);
                keys.forEach((v) => {
                    if (type === 'payment') state.removedPayments.delete(v);
                    if (type === 'expense') state.removedExpenses.delete(v);
                });
                if (type === 'payment') state.removedPaymentMonths.delete(month);
                if (type === 'expense') state.removedExpenseMonths.delete(month);
            }

            // === Filter buttons ===
            if (target.dataset.filter) {
                state.memberFilter = target.dataset.filter;
                renderMembers(state.previewData); // render only members to highlight deleted
                return;
            }

            // === Default re-render ===
            renderPreview(state.previewData, false);
            activate(state.activeTab);
        });
        showPlaceholder();
        if (options.openOnError) el.modal?.classList.add('open');
    }

    function expenditureController(options) {
        const state = { previewReady: false, previewData: null, activeTab: 'expenditures' };
        const el = {
            dialog: qs('expenditureImportDialog'),
            form: qs('expenditure-import-form'),
            fileInput: qs('expenditure-file-input'),
            fileInfo: qs('expenditure-file-info'),
            fileName: qs('expenditure-file-name'),
            dropZone: qs('expenditure-drop-zone'),
            dropLabel: qs('expenditure-drop-label'),
            importButton: qs('expenditure-import-btn'),
            previewSection: qs('expenditure-preview-section'),
            previewPlaceholder: qs('expenditure-preview-placeholder'),
            previewContent: qs('expenditure-preview-content'),
            tabs: qs('expenditure-tabs'),
            body: qs('expenditure-tab-body'),
            yearInput: qs('expenditure-import-year'),
            clearButton: qs('clearExpenditureImportFile'),
        };

        const placeholder = (text = 'No sheet uploaded') => {
            el.previewContent.style.display = 'none';
            el.previewPlaceholder.style.display = 'flex';
            el.previewPlaceholder.innerHTML = `<div><div style="font-weight:700;margin-bottom:6px;">${escapeHtml(text === 'No sheet uploaded' ? 'No sheet uploaded' : 'Preparing preview')}</div><div style="font-size:.82rem;">${escapeHtml(text)}</div></div>`;
        };

        const renderCards = () => {
            const entries = Object.entries((state.previewData?.expenditures || {}).months || {}).filter(([, info]) => (info.records_count || 0) > 0);
            if (!entries.length) return '<div class="preview-empty">No expenditures detected.</div>';
            return `<div class="preview-card-grid preview-tab-scroll">${entries.map(([monthName, info]) => `<div class="preview-glass preview-month-card"><div class="preview-month-title">${escapeHtml(monthName.toUpperCase())}</div><div class="preview-month-meta">Entries: <strong>${info.records_count || 0}</strong></div><div class="preview-month-meta">Total: <strong>${formatKES(info.total_amount || 0)}</strong></div><div class="preview-month-items">${(info.items || []).map((item) => `<div class="preview-month-item"><span class="name">${escapeHtml(item.name || 'Expenditure')}</span><span class="amount">${formatKES(item.amount || 0)}</span></div>`).join('')}</div></div>`).join('')}</div>`;
        };

        const renderErrors = () => {
            const errors = state.previewData?.errors || [];
            if (!errors.length) return '<div class="preview-empty">No errors found.</div>';
            return `<div class="preview-error-grid preview-tab-scroll">${errors.map((message) => `<div class="preview-glass preview-error-card"><div class="preview-error-head">Import Validation</div><div class="preview-error-body">${escapeHtml(message)}</div></div>`).join('')}</div>`;
        };

        const activate = (tabId) => {
            state.activeTab = tabId;
            el.body.className = 'preview-tab-body preview-tab-body-scrollable';
            el.body.innerHTML = tabId === 'errors' ? renderErrors() : renderCards();
            el.tabs.querySelectorAll('.year-tab-btn').forEach((button) => button.classList.toggle('active', button.dataset.id === tabId));
        };

        const renderPreview = (payload) => {
            state.previewData = payload;
            el.previewPlaceholder.style.display = 'none';
            el.previewContent.style.display = 'block';
            el.tabs.innerHTML = [createTabButton('expenditures', 'Expenditures', payload?.overview?.months_detected || 0), createTabButton('errors', 'Errors', (payload.errors || []).length)].join('');
            el.tabs.querySelectorAll('.year-tab-btn').forEach((button) => button.addEventListener('click', () => activate(button.dataset.id)));
            activate(state.activeTab || 'expenditures');
        };

        attachDropZone(el.dropZone, el.fileInput, async (file) => {
            el.dropLabel.textContent = 'File ready';
            el.fileInfo.style.display = 'flex';
            el.fileName.textContent = file.name;
            const formData = new FormData();
            formData.append('spreadsheet', file);
            formData.append('year', el.yearInput.value);
            el.dialog.style.maxWidth = '980px';
            el.previewSection.style.display = 'block';
            requestAnimationFrame(() => { el.previewSection.style.opacity = '1'; });
            placeholder('Generating preview... please wait');
            try {
                renderPreview(await postForm(options.previewUrl, formData));
                state.previewReady = true;
                el.importButton.disabled = false;
                el.importButton.textContent = 'Import';
            } catch {
                state.previewReady = false;
                el.previewPlaceholder.innerHTML = '<div class="preview-error-state"><div style="font-weight:700;margin-bottom:6px;">Import Preview Failed</div><div>The expenditure spreadsheet could not be read</div></div>';
            }
        });
        el.clearButton?.addEventListener('click', () => {
            el.fileInput.value = '';
            el.dropLabel.textContent = 'Drop your expenditures file here';
            el.fileInfo.style.display = 'none';
            placeholder();
            el.previewSection.style.opacity = '0';
            window.setTimeout(() => { el.previewSection.style.display = 'none'; }, 220);
            el.dialog.style.maxWidth = '580px';
            state.previewReady = false;
            state.previewData = null;
            el.importButton.disabled = true;
            el.importButton.textContent = 'Import';
        });
        el.form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!state.previewReady) return;
            const file = el.fileInput?.files?.[0];
            if (!file) return;
            const formData = new FormData();
            formData.append('spreadsheet', file);
            formData.append('year', el.yearInput.value);
            try {
                el.importButton.disabled = true;
                el.importButton.innerHTML = `${icons.spinner}<span>Importing...</span>`;
                await postForm(options.finalUrl, formData);
                window.location.reload();
            } catch (error) {
                window.alert(error.message || 'Expenditure import failed.');
                el.importButton.disabled = false;
                el.importButton.textContent = 'Import';
            }
        });
        placeholder();
    }

    document.addEventListener('DOMContentLoaded', () => {
        bindModal('importModal', 'openYearImportModal', ['closeYearImportModal', 'closeYearImportModalFooter']);
        bindModal('expenditureImportModal', 'openExpenditureImportModal', ['closeExpenditureImportModal', 'closeExpenditureImportModalFooter']);
        if (config.yearImport) yearController(config.yearImport);
        if (config.expenditureImport) expenditureController(config.expenditureImport);
    });
})();
