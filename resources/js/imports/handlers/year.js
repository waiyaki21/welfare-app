const buildMemberKey = (row) => `${row?.row || ''}|${String(row?.name || '').trim().toLowerCase()}|${row?.phone || ''}`;
const buildPaymentKey = (monthName, item) => `${item?.row || ''}|${String(item?.name || '').trim().toLowerCase()}|${item?.phone || ''}|${String(monthName || '').toUpperCase()}|${Number(item?.amount || 0).toFixed(2)}`;
const buildExpenseKey = (item) => `${item?.row || ''}|${String(item?.category || '').trim().toLowerCase()}|${item?.month || ''}|${Number(item?.amount || 0).toFixed(2)}`;

const normalizeName = (value) => String(value || '').trim().replace(/\s+/g, ' ').toLowerCase();
const normalizePhone = (value) => String(value || '').replace(/[^0-9+]/g, '');

const ensureMemberMeta = (rows) => {
    rows.forEach((row) => {
        if (!row.__original) {
            row.__original = { name: row.name, phone: row.phone };
        }
        if (row.__dbDuplicate === undefined) {
            row.__dbDuplicate = row.error_type === 'db_duplicate';
        }
    });
};

const computeDuplicateMaps = (rows, removedMembers) => {
    const nameRows = new Map();
    const phoneRows = new Map();

    rows.forEach((row) => {
        const key = buildMemberKey(row);
        if (removedMembers?.has(key)) return;
        const nameKey = normalizeName(row.name);
        const phoneKey = normalizePhone(row.phone);
        if (nameKey) {
            const list = nameRows.get(nameKey) || [];
            list.push(row.row);
            nameRows.set(nameKey, list);
        }
        if (phoneKey) {
            const list = phoneRows.get(phoneKey) || [];
            list.push(row.row);
            phoneRows.set(phoneKey, list);
        }
    });

    return { nameRows, phoneRows };
};

const recalculateMemberErrors = (state) => {
    const data = state.previewData || {};
    const membersInfo = data.members_info || {};
    const rows = membersInfo.members || [];
    ensureMemberMeta(rows);

    const { nameRows, phoneRows } = computeDuplicateMaps(rows, state.removedMembers);
    const errorMembers = [];

    rows.forEach((row) => {
        const errors = [];
        const nameKey = normalizeName(row.name);
        const phoneKey = normalizePhone(row.phone);
        const duplicateRowsByName = nameKey ? (nameRows.get(nameKey) || []) : [];
        const duplicateRowsByPhone = phoneKey ? (phoneRows.get(phoneKey) || []) : [];

        const hasSheetDuplicate = (duplicateRowsByName.length > 1 || duplicateRowsByPhone.length > 1);
        const hasGeneralError = !phoneKey;
        let hasDbDuplicate = false;

        if (row.__dbDuplicate && row.__original?.phone) {
            hasDbDuplicate = normalizePhone(row.phone) === normalizePhone(row.__original.phone);
        }

        if (duplicateRowsByName.length > 1) {
            const others = duplicateRowsByName.filter((r) => r !== row.row);
            if (others.length) {
                errors.push(`Name '${row.name}' appears multiple times in the sheet (also in rows: ${others.join(', ')}).`);
            }
        }

        if (!phoneKey) {
            errors.push(`Phone number is missing for '${row.name}'.`);
        }

        if (duplicateRowsByPhone.length > 1) {
            const others = duplicateRowsByPhone.filter((r) => r !== row.row);
            if (others.length) {
                errors.push(`Phone ${row.phone || '-'} is duplicated in the sheet (also in rows: ${others.join(', ')}).`);
            }
        }

        if (hasDbDuplicate && row.conflicts?.database_member) {
            const dbName = row.conflicts.database_member.name || 'Unknown';
            if (normalizeName(dbName) !== nameKey) {
                errors.push(`Phone ${row.phone || '-'} exists in database (sheet: '${row.name}', database: '${dbName}').`);
            } else {
                errors.push(`Phone ${row.phone || '-'} already exists in database as: '${dbName}'.`);
            }
        }

        let errorType = null;
        if (hasDbDuplicate) errorType = 'db_duplicate';
        else if (hasSheetDuplicate) errorType = 'sheet_duplicate';
        else if (hasGeneralError) errorType = 'general';

        row.errors = errors;
        row.error_type = errorType;

        const isRemoved = state.removedMembers?.has(buildMemberKey(row));
        if (errors.length && !isRemoved) {
            errorMembers.push({
                row: row.row,
                name: row.name,
                phone: row.phone,
                error_type: errorType,
                errors,
                conflicts: row.conflicts || {},
            });
        }
    });

    membersInfo.error_members = errorMembers;

    const activeIdentifiers = new Set();
    const allIdentifiers = new Set();

    rows.forEach((member) => {
        if (member.name) allIdentifiers.add(String(member.name).toLowerCase());
        if (member.phone) allIdentifiers.add(String(member.phone).toLowerCase());
        if (member.row) allIdentifiers.add(`row ${String(member.row).toLowerCase()}`);
    });

    errorMembers.forEach((member) => {
        if (member.name) activeIdentifiers.add(String(member.name).toLowerCase());
        if (member.phone) activeIdentifiers.add(String(member.phone).toLowerCase());
        if (member.row) activeIdentifiers.add(`row ${String(member.row).toLowerCase()}`);
    });

    const originalSheetErrors = state.sheetErrorsOriginal || [];
    state.sheetErrors = originalSheetErrors.filter((message) => {
        const lowered = String(message || '').toLowerCase();
        let matchesActive = false;
        let matchesAny = false;

        for (const identifier of activeIdentifiers) {
            if (identifier && lowered.includes(identifier)) {
                matchesActive = true;
                break;
            }
        }

        if (!matchesActive) {
            for (const identifier of allIdentifiers) {
                if (identifier && lowered.includes(identifier)) {
                    matchesAny = true;
                    break;
                }
            }
        }

        if (matchesActive) return true;
        if (matchesAny) return false;
        return true;
    });

    data.errors = state.sheetErrors;
    state.hasErrors = state.sheetErrors.length + errorMembers.length > 0;
};

const buildOverridePayload = (row) => ({
    row: row.row,
    name: row.name || '',
    phone: row.phone || '',
});

export const createYearHandler = () => ({
    initState(state) {
        state.memberFilter = 'all';
        state.removedMembers = new Set();
        state.removedPayments = new Set();
        state.removedExpenses = new Set();
        state.removedPaymentMonths = new Map();
        state.removedExpenseMonths = new Map();
        state.paymentMonthItems = new Map();
        state.expenseMonthItems = new Map();
        state.paymentMonthItemsAll = new Map();
        state.expenseMonthItemsAll = new Map();
        state.memberEdits = new Map();
        state.memberOverrides = new Map();
        state.sheetErrorsOriginal = [];
        state.sheetErrors = [];
    },
    resetState(state) {
        state.memberFilter = 'all';
        state.removedMembers?.clear();
        state.removedPayments?.clear();
        state.removedExpenses?.clear();
        state.removedPaymentMonths?.clear();
        state.removedExpenseMonths?.clear();
        state.paymentMonthItems?.clear();
        state.expenseMonthItems?.clear();
        state.paymentMonthItemsAll?.clear();
        state.expenseMonthItemsAll?.clear();
        state.memberEdits?.clear();
        state.memberOverrides?.clear();
        state.hasErrors = false;
        state.sheetErrorsOriginal = [];
        state.sheetErrors = [];
    },
    onPreviewSuccess(payload, state) {
        state.previewData = payload;
        state.sheetErrorsOriginal = Array.isArray(payload?.errors) ? payload.errors : [];
        state.sheetErrors = [...state.sheetErrorsOriginal];
        recalculateMemberErrors(state);
    },
    buildPreviewFormData() {
        // no-op for year
    },
    buildFinalFormData(formData, state) {
        formData.append('removed_members', JSON.stringify(Array.from(state.removedMembers || [])));
        formData.append('removed_payments', JSON.stringify(Array.from(state.removedPayments || [])));
        formData.append('removed_expenses', JSON.stringify(Array.from(state.removedExpenses || [])));
        const overrides = Array.from(state.memberOverrides?.values() || []);
        if (overrides.length) {
            formData.append('member_overrides', JSON.stringify(overrides));
        }
    },
    getErrorCount(state) {
        const data = state.previewData || {};
        const members = data.members_info || {};
        const sheetErrors = state.sheetErrors || data.errors || [];
        return sheetErrors.length + (members.error_members || []).length;
    },
    getScrollableTabs() {
        return ['payments', 'expenses', 'errors'];
    },
    getTabMeta(state, tabs) {
        const data = state.previewData || {};
        const members = data.members || data.members_info || {};
        const payments = data.payments || { months: {} };
        const expenses = data.expenses || { months: {} };
        const errorsCount = (state.sheetErrors || data.errors || []).length + (members.error_members || []).length;

        const paymentMonths = Object.values(payments.months || {}).filter((month) => (month.items || []).length > 0).length;
        const expenseMonths = Object.values(expenses.months || {}).filter((month) => (month.items || []).length > 0).length;
        const memberCount = (members.members || []).length || (members.total_count || 0);

        return tabs.map((tab) => {
            if (tab === 'members') return { id: tab, label: 'Members', count: memberCount };
            if (tab === 'payments') return { id: tab, label: 'Payments', count: paymentMonths };
            if (tab === 'expenses') return { id: tab, label: 'Expenses', count: expenseMonths };
            if (tab === 'errors') return { id: tab, label: 'Errors', count: errorsCount };
            if (tab === 'summary') return { id: tab, label: 'Summary' };
            return { id: tab, label: tab.charAt(0).toUpperCase() + tab.slice(1) };
        });
    },
    renderTab(tabId, state, helpers) {
        if (tabId === 'summary') return renderOverview(state.previewData || {}, helpers);
        if (tabId === 'members') return renderMembers(state, helpers);
        if (tabId === 'payments') return renderMonthCards(state, 'payment', helpers);
        if (tabId === 'expenses') return renderMonthCards(state, 'expense', helpers);
        if (tabId === 'errors') return renderErrors(state, helpers);
        return '';
    },
    bindPreviewEvents({ elements, state, preview, helpers }) {
        elements.body?.addEventListener('click', (event) => {
            const target = event.target.closest('[data-action], [data-filter]');
            if (!target) return;

            const { action, key, type, month, value, row } = target.dataset;

            if (action === 'copy' && value) {
                navigator.clipboard.writeText(value).then(() => {
                    target.textContent = 'COPIED';
                    target.classList.add('copied');
                    setTimeout(() => {
                        target.textContent = 'Copy';
                        target.classList.remove('copied');
                    }, 2000);
                });
                return;
            }

            if (action === 'undo-member' && key) state.removedMembers.delete(key);
            if (action === 'remove-member' && key) state.removedMembers.add(key);
            if (action === 'remove-payment' && key) state.removedPayments.add(key);
            if (action === 'remove-expense' && key) state.removedExpenses.add(key);

            if (action === 'remove-month' && type && month) {
                const items = type === 'payment'
                    ? (state.paymentMonthItemsAll.get(month) || [])
                    : (state.expenseMonthItemsAll.get(month) || []);
                const keys = items.map((item) => type === 'payment' ? buildPaymentKey(month, item) : buildExpenseKey(item));
                if (type === 'payment') {
                    keys.forEach((v) => state.removedPayments.add(v));
                    state.removedPaymentMonths.set(month, keys);
                } else {
                    keys.forEach((v) => state.removedExpenses.add(v));
                    state.removedExpenseMonths.set(month, keys);
                }
            }
            if (action === 'undo-month' && type && month) {
                const keys = type === 'payment'
                    ? (state.removedPaymentMonths.get(month) || [])
                    : (state.removedExpenseMonths.get(month) || []);
                keys.forEach((v) => {
                    if (type === 'payment') state.removedPayments.delete(v);
                    if (type === 'expense') state.removedExpenses.delete(v);
                });
                if (type === 'payment') state.removedPaymentMonths.delete(month);
                if (type === 'expense') state.removedExpenseMonths.delete(month);
            }
            if (action === 'undo-month-items' && type && month) {
                const items = type === 'payment'
                    ? (state.paymentMonthItemsAll.get(month) || [])
                    : (state.expenseMonthItemsAll.get(month) || []);
                const keys = items.map((item) => type === 'payment' ? buildPaymentKey(month, item) : buildExpenseKey(item));
                keys.forEach((v) => {
                    if (type === 'payment') state.removedPayments.delete(v);
                    if (type === 'expense') state.removedExpenses.delete(v);
                });
            }

            if (action === 'edit-member' && row) {
                state.memberEdits.set(row, { editing: true });
            }
            if (action === 'cancel-edit' && row) {
                state.memberEdits.delete(row);
            }
            if (action === 'save-member' && row) {
                const card = target.closest('[data-member-card]');
                const nameInput = card?.querySelector('[data-role="edit-name"]');
                const phoneInput = card?.querySelector('[data-role="edit-phone"]');
                const newName = nameInput?.value?.trim() || '';
                const newPhone = phoneInput?.value?.trim() || '';

                const data = state.previewData || {};
                const members = data.members_info || {};
                const rows = members.members || [];
                const member = rows.find((r) => String(r.row) === String(row));
                if (member) {
                    const oldKey = buildMemberKey(member);
                    member.name = newName;
                    member.phone = newPhone;
                    const newKey = buildMemberKey(member);
                    if (state.removedMembers.has(oldKey)) {
                        state.removedMembers.delete(oldKey);
                        state.removedMembers.add(newKey);
                    }
                    state.memberOverrides.set(String(row), buildOverridePayload(member));
                    state.memberEdits.delete(row);
                }
            }
            if (action === 'merge-member' && row) {
                const data = state.previewData || {};
                const members = data.members_info || {};
                const rows = members.members || [];
                const member = rows.find((r) => String(r.row) === String(row));
                const matchSource = member?.conflicts?.database_member || member?.possible_match?.member;
                if (member && matchSource) {
                    const oldKey = buildMemberKey(member);
                    member.name = matchSource.name || member.name;
                    member.phone = matchSource.phone || member.phone;
                    member.status = 'existing';
                    member.__dbDuplicate = false;
                    member.possible_match = null;
                    const newKey = buildMemberKey(member);
                    if (state.removedMembers.has(oldKey)) {
                        state.removedMembers.delete(oldKey);
                        state.removedMembers.add(newKey);
                    }
                    state.memberOverrides.set(String(row), buildOverridePayload(member));
                }
            }

            if (target.dataset.filter) {
                event.preventDefault();
                state.memberFilter = target.dataset.filter;
                recalculateMemberErrors(state);
                preview.refresh();
                helpers?.updateImportButtonState?.();
                return;
            }

            recalculateMemberErrors(state);
            preview.refresh();
            helpers?.updateImportButtonState?.();
        });
    }
});

const renderErrors = (state, { escapeHtml }) => {
    const data = state.previewData || {};
    const members = data.members_info || {};
    const sheetErrors = state.sheetErrors || data.errors || [];
    const memberErrors = (members.error_members || []).flatMap((entry) => (entry.errors || []).map((message) => ({
        title: `${entry.name || 'Unknown member'}${entry.phone ? ` (${entry.phone})` : ''}`,
        message,
    })));
    const genericErrors = (sheetErrors || []).map((message) => ({ title: 'General Validation', message }));
    const errors = [...memberErrors, ...genericErrors];
    if (!errors.length) return '<div class="preview-empty">No errors found.</div>';
    return `<div class="preview-error-grid preview-tab-scroll">${errors.map((error) => `
        <div class="preview-glass preview-error-card">
            <div class="preview-error-head">${escapeHtml(error.title)}</div>
            <div class="preview-error-body">${escapeHtml(error.message)}</div>
        </div>
    `).join('')}</div>`;
};

const renderMembers = (state, { escapeHtml, icons, formatKES }) => {
    const data = state.previewData || {};
    const members = data.members_info || {};
    const allMembers = members.members || [];

    ensureMemberMeta(allMembers);

    const rows = allMembers.map((row) => {
        const key = buildMemberKey(row);
        const isDeleted = state.removedMembers.has(key);
        const hasErrors = (row.errors || []).length > 0;
        const editState = state.memberEdits.get(String(row.row)) || {};

        return {
            ...row,
            key,
            isDeleted,
            hasErrors,
            editing: editState.editing,
        };
    });

    const counts = {
        deleted: rows.filter((r) => r.isDeleted).length,
        total: rows.length,
        existing: rows.filter((r) => r.status === 'existing' && !r.isDeleted).length,
        new: rows.filter((r) => r.status === 'new' && !r.isDeleted).length,
        errors: rows.filter((r) => (r.errors || []).length > 0 && !r.isDeleted).length,
    };

    const filtered = rows.filter((row) => {
        if (state.memberFilter === 'existing') return row.status === 'existing' && !row.isDeleted;
        if (state.memberFilter === 'new') return row.status === 'new' && !row.isDeleted;
        if (state.memberFilter === 'errors') return (row.errors || []).length > 0 && !row.isDeleted;
        if (state.memberFilter === 'deleted') return row.isDeleted;
        return true;
    });

    const emptyMessages = {
        existing: 'No existing members found',
        new: 'No new members found',
        errors: 'No members with errors',
        deleted: 'No deleted members',
        all: 'No members found',
    };

    return `
    <div class="preview-glass preview-members">
        <div class="preview-members-summary flex justify-between items-center">
            <div class="filters-left flex gap-2">
                <button type="button" class="filter-chip ${state.memberFilter === 'existing' ? 'active' : ''}" data-filter="existing"> Existing: <strong>${counts.existing}</strong> </button>
                <button type="button" class="filter-chip ${state.memberFilter === 'new' ? 'active' : ''}" data-filter="new"> New: <strong>${counts.new}</strong> </button>
                <button type="button" class="filter-chip ${state.memberFilter === 'errors' ? 'active' : ''}" data-filter="errors"> Errors: <strong>${counts.errors}</strong> </button>
                ${counts.deleted > 0 ? `<button type="button" class="filter-chip ${state.memberFilter === 'deleted' ? 'active' : ''}" data-filter="deleted"> Deleted: <strong>${counts.deleted}</strong> </button>` : ''}
            </div>
            <div class="filters-right">
                <button type="button" class="filter-chip ${state.memberFilter === 'all' ? 'active' : ''}" data-filter="all"> View All: <strong>${counts.total}</strong> </button>
            </div>
        </div>
        <div class="member-card-grid preview-tab-scroll">
            ${filtered.length ? filtered.map((row) => {
        const statusClass = row.isDeleted
            ? 'member-card-deleted'
            : row.hasErrors
                ? 'member-card-error'
                : row.status === 'new'
                    ? 'member-card-new'
                    : 'member-card-existing';
        const showEdit = row.status === 'new' && row.error_type === 'sheet_duplicate' && !row.isDeleted;
        const matchScore = row.possible_match?.final_match || 0;
        const showMerge = row.status === 'new' && !row.isDeleted
            && (row.error_type === 'db_duplicate' || matchScore >= 70);
        const statusLabel = row.isDeleted
            ? 'Deleted'
            : (row.status === 'new' && matchScore >= 65)
                ? `New : ${Math.round(matchScore)}% Match`
                : row.hasErrors
                    ? 'Error'
                    : row.status === 'new'
                        ? 'New'
                        : 'Existing';
        const statusPillClass = row.isDeleted
            ? 'member-pill-deleted'
            : row.hasErrors
                ? 'member-pill-error'
                : row.status === 'new'
                    ? 'member-pill-new'
                    : 'member-pill-existing';
        const totalContributions = Number(row.total_contributions || 0);
        const monthsFromContrib = Array.isArray(row.months_contributed) ? row.months_contributed.length : 0;
        const contributionsLabel = `${formatKES(totalContributions)} (${monthsFromContrib} ${monthsFromContrib != 1 ? `Month` : `Months`})`;
        return `
                    <div class="member-card ${statusClass}" data-member-card data-row="${escapeHtml(row.row)}">
                        <div class="member-card-head">
                            <div>
                                <div class="member-card-name">${escapeHtml(row.name || '-')}</div>
                                <div class="member-card-sub">
                                    <span>${escapeHtml(row.phone || 'No phone')}${row.row ? ` : row ${escapeHtml(row.row)}` : ''}</span>
                                    <span class="member-pill ${statusPillClass}">${statusLabel}</span>
                                </div>
                            </div>
                            <div class="member-card-actions">
                                ${showEdit && !row.editing ? `
                                    <button type="button" class="member-action-btn" data-action="edit-member" data-row="${escapeHtml(row.row)}" title="Edit">${icons.edit}</button>
                                ` : ''}
                                ${showMerge ? `
                                    <button type="button" class="member-action-btn member-action-merge" data-action="merge-member" data-row="${escapeHtml(row.row)}" title="Merge">${icons.merge}</button>
                                ` : ''}
                                ${row.isDeleted
                ? `<button type="button" class="member-action-btn member-action-undo" data-action="undo-member" data-key="${escapeHtml(row.key)}" title="Undo">${icons.undo}</button>`
                : `<button type="button" class="member-action-btn member-action-remove" data-action="remove-member" data-key="${escapeHtml(row.key)}" title="Delete">${icons.trash}</button>`}
                            </div>
                        </div>
                        ${row.editing ? `
                            <div class="member-edit-grid">
                                <div>
                                    <label class="member-edit-label">Name</label>
                                    <input type="text" class="member-edit-input" data-role="edit-name" value="${escapeHtml(row.name || '')}">
                                </div>
                                <div>
                                    <label class="member-edit-label">Phone</label>
                                    <input type="text" class="member-edit-input" data-role="edit-phone" value="${escapeHtml(row.phone || '')}">
                                </div>
                                <div class="member-edit-actions">
                                    <button type="button" class="member-action-btn member-action-save" data-action="save-member" data-row="${escapeHtml(row.row)}" title="Save">${icons.check}</button>
                                    <button type="button" class="member-action-btn member-action-cancel" data-action="cancel-edit" data-row="${escapeHtml(row.row)}" title="Cancel">${icons.close}</button>
                                </div>
                            </div>
                        ` : `
                            <div class="member-card-stats">
                                <div>
                                    <div class="member-stat-label">C/F</div>
                                    <div class="member-stat-value">${formatKES(row.contributions_carried_forward || 0)}</div>
                                </div>
                                <div>
                                    <div class="member-stat-label">Total Contributions</div>
                                    <div class="member-stat-value">${contributionsLabel}</div>
                                </div>
                                <div>
                                    <div class="member-stat-label">Total Welfare</div>
                                    <div class="member-stat-value">${formatKES(row.total_welfare || 0)}</div>
                                </div>
                                <div>
                                    <div class="member-stat-label">Total Investment</div>
                                    <div class="member-stat-value">${formatKES(row.total_investment || 0)}</div>
                                </div>
                            </div>
                            ${row.possible_match ? `
                                <div class="member-card-match">
                                    <div class="member-match-label">Suggested Member</div>
                                    <div class="member-match-value">${escapeHtml(row.possible_match.member?.name || 'Unknown')}</div>
                                    <div class="member-match-sub">${escapeHtml(row.possible_match.member?.phone || 'No phone')}</div>
                                </div>
                            ` : ''}
                        `}
                        ${(row.errors || []).length ? `
                            <div class="member-card-errors">
                                ${(row.errors || []).map((err) => `<div>${escapeHtml(err)}</div>`).join('')}
                            </div>
                        ` : ''}
                    </div>
                `;
    }).join('') : `
                <div class="preview-empty preview-empty-state">
                    ${escapeHtml(emptyMessages[state.memberFilter] || emptyMessages.all)}
                </div>
            `}
        </div>
    </div>
    `;
};

const renderOverview = (data, { escapeHtml, formatKES }) => {
    const info = data.sheet_info || [];

    return `
    <div class="preview-glass preview-overview">
        <div class="preview-summary-grid">
            ${info.map((item) => {
        let valueHtml = '';
        let rawValue = item.value;

        if (item.type === 'count') {
            const count = Array.isArray(rawValue) ? rawValue.length : (rawValue || 0);
            valueHtml = `<span class="value-text-bold">${count}</span>`;
        } else if (item.type === 'currency') {
            valueHtml = `<span class="value-text-bold">${formatKES(rawValue || 0)}</span>`;
        } else if (item.type === 'copy') {
            valueHtml = `
                        <div class="copy-field-container">
                            <span class="copy-value-text">${escapeHtml(rawValue)}</span>
                            <button type="button" class="copy-action-btn" data-action="copy" data-value="${escapeHtml(rawValue)}">Copy</button>
                        </div>
                    `;
        } else if (item.type === 'list' && Array.isArray(rawValue)) {
            valueHtml = `
                        <div class="list-pill-wrapper">
                            ${rawValue.map((v) => `<span class="preview-pill">${escapeHtml(v)}</span>`).join('')}
                        </div>
                    `;
        } else {
            valueHtml = `<span class="value-text-bold">${escapeHtml(rawValue)}</span>`;
        }

        return `
                    <div class="preview-summary-item ${item.full ? 'grid-full' : ''}">
                        <div class="preview-summary-label">${escapeHtml(item.label)}</div>
                        <div class="preview-summary-value-content">${valueHtml}</div>
                    </div>
                `;
    }).join('')}
        </div>
    </div>`;
};

const renderMonthCards = (state, type, { escapeHtml, formatKES, icons }) => {
    const data = state.previewData || {};
    const months = type === 'payment'
        ? ((data.payments || {}).months || {})
        : ((data.expenses || {}).months || {});

    const entries = Object.entries(months).map(([monthName, info]) => {
        const allItems = info.items || [];
        const items = allItems.filter((item) => type === 'payment'
            ? !state.removedPayments.has(buildPaymentKey(monthName, item))
            : !state.removedExpenses.has(buildExpenseKey(item))
        );
        if (type === 'payment') state.paymentMonthItems.set(monthName, items);
        if (type === 'expense') state.expenseMonthItems.set(monthName, items);
        if (type === 'payment') state.paymentMonthItemsAll.set(monthName, allItems);
        if (type === 'expense') state.expenseMonthItemsAll.set(monthName, allItems);

        return [monthName, {
            allItems,
            items,
            deleted: type === 'payment'
                ? state.removedPaymentMonths.has(monthName)
                : state.removedExpenseMonths.has(monthName),
            total: items.reduce((sum, item) => sum + Number(item.amount || 0), 0),
        }];
    }).filter(([, info]) => info.allItems.length > 0 || info.deleted);

    if (!entries.length) return `<div class="preview-empty">No ${type === 'payment' ? 'monthly payments' : 'expenses'} detected.</div>`;

    return `<div class="preview-card-grid preview-tab-scroll">${entries.map(([monthName, info]) => {
        const emptyRemoved = !info.deleted && info.allItems.length > 0 && info.items.length === 0;
        if (info.deleted) {
            return `
                <div class="preview-glass preview-month-card preview-month-card-deleted">
                    <div>
                        <div class="preview-month-title">${escapeHtml(monthName.toUpperCase())}</div>
                        <div class="preview-month-meta">This month has been removed from preview.</div>
                    </div>
                    <button type="button" class="preview-undo-btn" data-action="undo-month" data-type="${type}" data-month="${escapeHtml(monthName)}">Undo Delete</button>
                </div>
            `;
        }
        if (emptyRemoved) {
            return `
                <div class="preview-glass preview-month-card preview-month-card-deleted">
                    <div>
                        <div class="preview-month-title">${escapeHtml(monthName.toUpperCase())}</div>
                        <div class="preview-month-meta">All items deleted.</div>
                    </div>
                    <button type="button" class="preview-undo-btn" data-action="undo-month-items" data-type="${type}" data-month="${escapeHtml(monthName)}">Undo Delete</button>
                </div>
            `;
        }

        return `
            <div class="preview-glass preview-month-card">
                <div class="preview-month-head">
                    <div>
                        <div class="preview-month-title">${escapeHtml(monthName.toUpperCase())}</div>
                        <div class="preview-month-meta">${type === 'payment' ? 'Payments' : 'Expenses'}: <strong>${info.items.length}</strong></div>
                        <div class="preview-month-meta">Total: <strong>${formatKES(info.total)}</strong></div>
                    </div>
                    <button type="button" class="preview-month-delete-btn" data-action="remove-month" data-type="${type}" data-month="${escapeHtml(monthName)}">${icons.trash}<span>Delete X</span></button>
                </div>
                <div class="preview-month-items">
                    ${info.items.map((item) => `
                        <div class="preview-month-item">
                            <div class="name">${escapeHtml(type === 'payment' ? (item.name || 'Unknown') : (item.category || 'Expense'))}</div>
                            <div class="amount">${formatKES(item.amount || 0)}</div>
                            <button type="button" class="preview-remove-btn" data-action="remove-${type}" data-key="${escapeHtml(type === 'payment' ? buildPaymentKey(monthName, item) : buildExpenseKey(item))}">
                                ${icons.trash}<span>Delete X</span>
                            </button>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }).join('')}</div>`;
};

