export const icons = {
    trash: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="m19 6-1 14H6L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path></svg>',
    spinner: '<svg class="preview-spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>',
    undo: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 14 4 9l5-5"></path><path d="M20 20a8 8 0 0 0-11-11L4 9"></path></svg>',
    upload: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>',
    alert: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
    edit: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg>',
    merge: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="18" r="3"></circle><circle cx="6" cy="6" r="3"></circle><path d="M6 9v6a6 6 0 0 0 6 6h3"></path><path d="M6 9a6 6 0 0 1 6-6h3"></path></svg>',
    check: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"></path></svg>',
    close: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>'
};

export const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

export const formatKES = (value) => `KSH ${Number(value || 0).toLocaleString()}`;

const titleCase = (value) => value
    .split(/\s|_/)
    .filter(Boolean)
    .map((chunk) => chunk.charAt(0).toUpperCase() + chunk.slice(1))
    .join(' ');

const createTabButton = (id, title, count) => {
    const badge = typeof count === 'number' ? `<span class="year-tab-badge">${count}</span>` : '';
    return `<button type="button" class="year-tab-btn" data-tab="${id}"><span>${title}</span>${badge}</button>`;
};

export const createPreviewUI = ({ elements, state, tabs, handler }) => {
    const setErrorState = (enabled) => {
        elements.fileInfo?.classList.toggle('import-file-info-error', enabled);
        elements.importButton?.classList.toggle('import-btn-error', enabled);
        if (enabled) {
            elements.importButton.disabled = true;
        }
    };

    const showPlaceholder = (text = 'No sheet uploaded') => {
        elements.previewContent.style.display = 'none';
        elements.previewPlaceholder.style.display = 'flex';
        const loading = text.toLowerCase().includes('generating preview');
        elements.previewPlaceholder.innerHTML = `
            <div>
                ${loading ? '<div class="preview-spinner"></div>' : ''}
                <div style="font-weight:700;margin-bottom:6px;">${escapeHtml(text === 'No sheet uploaded' ? 'No sheet uploaded' : 'Preparing preview')}</div>
                <div style="font-size:.82rem;">${escapeHtml(text)}</div>
            </div>
        `;
    };

    const showError = (message = 'Spreadsheet could not be processed', errors = []) => {
        elements.previewContent.style.display = 'none';
        elements.previewPlaceholder.style.display = 'flex';
        const list = Array.isArray(errors) && errors.length
            ? `<ul class="preview-error-list">${errors.map((err) => `<li class="preview-error-item">${escapeHtml(err)}</li>`).join('')}</ul>`
            : '';
        elements.previewPlaceholder.innerHTML = `
            <div class="preview-error-state">
                <div class="preview-error-title">Import Preview Failed</div>
                <div class="preview-error-message">${escapeHtml(message)}</div>
                ${list}
            </div>
        `;
        setErrorState(true);
    };

    const renderTabs = () => {
        const meta = handler?.getTabMeta ? handler.getTabMeta(state, tabs) : null;
        const items = (meta && meta.length ? meta : tabs.map((tab) => ({ id: tab, label: titleCase(tab) })));
        elements.tabs.innerHTML = items.map((item) => createTabButton(item.id, item.label, item.count)).join('');
        elements.tabs.querySelectorAll('.year-tab-btn').forEach((button) => {
            button.addEventListener('click', () => activate(button.dataset.tab));
        });
    };

    const activate = (tabId) => {
        state.activeTab = tabId;
        const previousScroll = elements.body.querySelector('.preview-tab-scroll')?.scrollTop ?? 0;
        elements.body.className = 'preview-tab-body';
        if (handler?.getScrollableTabs?.(state)?.includes(tabId)) {
            elements.body.classList.add('preview-tab-body-scrollable');
        }
        elements.body.innerHTML = handler?.renderTab
            ? handler.renderTab(tabId, state, { escapeHtml, formatKES, icons })
            : '';
        elements.tabs.querySelectorAll('.year-tab-btn').forEach((button) => {
            button.classList.toggle('active', button.dataset.tab === tabId);
        });
        const nextScroll = elements.body.querySelector('.preview-tab-scroll');
        if (nextScroll) {
            nextScroll.scrollTop = previousScroll;
        }
    };

    const render = (payload) => {
        state.previewData = payload;
        elements.previewPlaceholder.style.display = 'none';
        elements.previewContent.style.display = 'block';
        renderTabs();
        activate(state.activeTab || tabs[0] || 'summary');
        setErrorState(false);
    };

    const refresh = () => activate(state.activeTab || tabs[0] || 'summary');

    return {
        showPlaceholder,
        showError,
        render,
        refresh,
        activate,
        setErrorState,
    };
};
