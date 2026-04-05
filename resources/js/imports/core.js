import { postForm } from './uploader';
import { createPreviewUI, icons, escapeHtml } from './preview';
import { createBaseState, resetBaseState } from './state';

const attachDropZone = (dropZone, fileInput, onFile) => {
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
};

const mapElements = (root) => ({
    modal: root,
    dialog: root.querySelector('[data-role="dialog"]'),
    form: root.querySelector('[data-role="form"]'),
    formSection: root.querySelector('[data-role="form-section"]'),
    fileInput: root.querySelector('[data-role="file-input"]'),
    fileInfo: root.querySelector('[data-role="file-info"]'),
    fileName: root.querySelector('[data-role="file-name"]'),
    dropZone: root.querySelector('[data-role="drop-zone"]'),
    dropLabel: root.querySelector('[data-role="drop-label"]'),
    importButton: root.querySelector('[data-role="import-button"]'),
    previewSection: root.querySelector('[data-role="preview-section"]'),
    previewPlaceholder: root.querySelector('[data-role="preview-placeholder"]'),
    previewContent: root.querySelector('[data-role="preview-content"]'),
    tabs: root.querySelector('[data-role="preview-tabs"]'),
    body: root.querySelector('[data-role="preview-body"]'),
    clearButtons: root.querySelectorAll('[data-role="clear-file"]'),
    lastUploadToggle: root.querySelector('[data-role="last-upload-toggle"]'),
    lastUploadStatus: root.querySelector('[data-role="last-upload-status"]'),
    lastUploadCard: root.querySelector('[data-role="last-upload-card"]'),
    lastUploadChip: root.querySelector('[data-role="last-upload-chip"]'),
    lastUploadDismiss: root.querySelector('[data-role="dismiss-last-upload"]'),
    fileChip: root.querySelector('[data-role="file-chip"]'),
    fileChipName: root.querySelector('[data-role="file-chip-name"]'),
    fileChipClear: root.querySelector('[data-role="file-chip-clear"]'),
    closeButtons: root.querySelectorAll('[data-role="close-modal"]'),
    templateYear: root.querySelector('[data-role="template-year"]'),
    templateMonth: root.querySelector('[data-role="template-month"]'),
    downloadTemplate: root.querySelector('[data-role="download-template"]'),
    uploadYear: root.querySelector('[data-role="upload-year"]'),
    uploadMonth: root.querySelector('[data-role="upload-month"]'),
    yearInput: root.querySelector('[data-role="year-input"]'),
});

const bindModalControls = (root, modalId) => {
    const openers = document.querySelectorAll(`[data-modal-open="${modalId}"]`);
    openers.forEach((button) => button.addEventListener('click', () => root.classList.add('open')));

    root.addEventListener('click', (event) => {
        if (event.target === root) root.classList.remove('open');
    });
};

export const initImportModal = ({ root, previewUrl, finalUrl, lastUploadUrl, tabs, handler, openOnError }) => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const state = createBaseState();
    handler?.initState?.(state);

    const elements = mapElements(root);
    const preview = createPreviewUI({ elements, state, tabs, handler });
    const importButtonHtml = elements.importButton?.innerHTML || '';

    const setPreviewOnly = (enabled) => {
        if (!elements.dialog) return;
        elements.dialog.classList.toggle('import-preview-only', enabled);
        if (elements.formSection) {
            elements.formSection.style.display = enabled ? 'none' : '';
        }
        if (elements.previewSection && enabled) {
            elements.previewSection.style.display = 'block';
            requestAnimationFrame(() => { elements.previewSection.style.opacity = '1'; });
        }
        if (!enabled && elements.previewSection) {
            elements.previewSection.style.opacity = '0';
            window.setTimeout(() => { elements.previewSection.style.display = 'none'; }, 220);
        }
    };

    const expand = () => {
        if (!elements.dialog || !elements.previewSection) return;
        elements.dialog.style.maxWidth = '1080px';
        setPreviewOnly(true);
    };

    const collapse = () => {
        if (!elements.dialog || !elements.previewSection) return;
        elements.dialog.style.maxWidth = '580px';
        setPreviewOnly(false);
    };

    const reset = () => {
        resetBaseState(state);
        handler?.resetState?.(state);
    };

    const showPreviewErrorFromResponse = (error) => {
        if (error?.status === 422) {
            preview.showError(error?.data?.message || 'Spreadsheet could not be processed', error?.data?.errors || []);
        } else {
            preview.showError();
        }
    };

    const updateImportButtonState = (mode = '') => {
        if (!elements.importButton) return;
        const hasErrors = handler?.getErrorCount?.(state) > 0;
        const ready = state.previewReady && !hasErrors;

        let label = elements.importButton.dataset.label || 'Import';
        let icon = icons.upload;
        let disabled = true;

        if (mode === 'loading') {
            icon = icons.spinner;
            label = 'Importing...';
            disabled = true;
        } else if (state.previewReady && hasErrors) {
            const errorCount = handler?.getErrorCount?.(state) || 0;
            icon = icons.alert;
            label = `Resolve Errors (${errorCount})`;
            disabled = true;
        } else if (ready) {
            icon = icons.upload;
            label = elements.importButton.dataset.label || 'Import';
            disabled = false;
        }

        elements.importButton.disabled = disabled;
        elements.importButton.classList.toggle('import-btn-error', state.previewReady && hasErrors);
        elements.importButton.classList.toggle('import-btn-disabled', !state.previewReady);
        elements.importButton.innerHTML = `${icon}<span>${label}</span>`;
    };

    const previewFile = async () => {
        const file = elements.fileInput?.files?.[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('spreadsheet', file);
        handler?.buildPreviewFormData?.(formData, state, elements);
        expand();
        preview.showPlaceholder('Generating preview... please wait');
        updateImportButtonState();
        try {
            const payload = await postForm(previewUrl, formData, csrfToken);
            preview.render(payload);
            handler?.onPreviewSuccess?.(payload, state, elements);
            state.previewReady = true;
            updateImportButtonState();
        } catch (error) {
            state.previewReady = false;
            showPreviewErrorFromResponse(error);
            updateImportButtonState();
        }
    };

    attachDropZone(elements.dropZone, elements.fileInput, (file) => {
        state.selectedFile = file;
        if (elements.dropLabel) elements.dropLabel.textContent = 'File ready';
        if (elements.fileInfo) elements.fileInfo.style.display = 'flex';
        if (elements.fileName) elements.fileName.textContent = file.name;
        if (elements.fileChip && elements.fileChipName) {
            elements.fileChipName.textContent = file.name;
            elements.fileChip.style.display = 'inline-flex';
        }
        if (elements.lastUploadToggle?.checked) {
            elements.lastUploadToggle.checked = false;
            state.usingLastUpload = false;
        }
        preview.setErrorState(false);
        // Hide the upload form section once a file is chosen (monthly/expenditure modals)
        if (root.dataset.hideFormOnFile === '1' && elements.formSection) {
            elements.formSection.style.display = 'none';
        }
        previewFile();
    });

    const clearFile = () => {
        if (elements.fileInput) elements.fileInput.value = '';
        if (elements.dropLabel) elements.dropLabel.textContent = elements.dropLabel.dataset.default || 'Drop your .xlsx file here';
        if (elements.fileInfo) elements.fileInfo.style.display = 'none';
        if (elements.fileChip) elements.fileChip.style.display = 'none';
        // Restore form section for hide-on-file modals
        if (root.dataset.hideFormOnFile === '1' && elements.formSection) {
            elements.formSection.style.display = '';
        }
        // For selection-driven modals (monthly/expenditure), don't fully collapse —
        // the handler will re-show the month/year check panel via a custom event
        const isSelectionDriven = root.dataset.hideFormOnFile === '1';
        if (!isSelectionDriven) {
            preview.showPlaceholder();
            collapse();
        }
        reset();
        updateImportButtonState();
        preview.setErrorState(false);
        // Let handlers re-trigger their selection-based preview
        root.dispatchEvent(new CustomEvent('import:file-cleared'));
    };

    elements.clearButtons?.forEach((button) => {
        button.addEventListener('click', clearFile);
    });

    elements.fileChip?.addEventListener('click', clearFile);
    elements.fileChipClear?.addEventListener('click', (event) => {
        event.stopPropagation();
        clearFile();
    });

    elements.form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!state.previewReady) return;

        const formData = new FormData();

        if (state.usingLastUpload) {
            formData.append('use_last_upload', 'true');
        } else {
            const file = state.selectedFile || elements.fileInput?.files?.[0];
            if (!file) return;
            formData.append('spreadsheet', file);
        }

        handler?.buildFinalFormData?.(formData, state, elements);

        try {
            updateImportButtonState('loading');
            await postForm(finalUrl || elements.form.action, formData, csrfToken);
            window.location.reload();
        } catch (error) {
            window.alert(error.message || 'Import failed.');
            updateImportButtonState();
        }
    });

    if (elements.lastUploadToggle) {
        elements.lastUploadToggle.addEventListener('change', async () => {
            const isOn = elements.lastUploadToggle.checked;
            const statusEl = elements.lastUploadStatus;

            if (isOn) {
                if (elements.dropZone) elements.dropZone.style.display = 'none';
                if (elements.fileInfo) elements.fileInfo.style.display = 'none';
                if (elements.fileChip) elements.fileChip.style.display = 'none';
                if (elements.fileInput) elements.fileInput.value = '';
                state.selectedFile = null;
                preview.setErrorState(false);
                if (statusEl) {
                    statusEl.style.display = 'block';
                    statusEl.textContent = 'Loading preview from saved file...';
                }

                expand();
                preview.showPlaceholder('Generating preview... please wait');
                updateImportButtonState();

                try {
                    const formData = new FormData();
                    formData.append('type', root.dataset.importType || 'year');
                    const payload = await postForm(lastUploadUrl, formData, csrfToken);
                    preview.render(payload);
                    handler?.onPreviewSuccess?.(payload, state, elements);
                    state.previewReady = true;
                    state.usingLastUpload = true;
                    updateImportButtonState();
                    if (statusEl) statusEl.textContent = 'Preview loaded from saved file.';
                } catch (error) {
                    state.previewReady = false;
                    state.usingLastUpload = false;
                    showPreviewErrorFromResponse(error);
                    elements.lastUploadToggle.checked = false;
                    if (elements.dropZone) elements.dropZone.style.display = '';
                    if (statusEl) {
                        statusEl.textContent = error.message || 'Could not load saved file.';
                    }
                }
            } else {
                state.usingLastUpload = false;
                state.previewReady = false;
                if (elements.dropZone) elements.dropZone.style.display = '';
                updateImportButtonState();
                if (statusEl) {
                    statusEl.style.display = 'none';
                    statusEl.textContent = '';
                }
                collapse();
                reset();
                preview.showPlaceholder();
            }
        });
    }

    const dismissLastUpload = () => {
        if (elements.lastUploadChip) elements.lastUploadChip.style.display = 'none';
        if (elements.lastUploadCard) elements.lastUploadCard.style.display = 'none';
        if (elements.lastUploadToggle?.checked) {
            elements.lastUploadToggle.checked = false;
            state.usingLastUpload = false;
            state.previewReady = false;
            if (elements.dropZone) elements.dropZone.style.display = '';
            if (elements.lastUploadStatus) {
                elements.lastUploadStatus.style.display = 'none';
                elements.lastUploadStatus.textContent = '';
            }
            preview.showPlaceholder();
            collapse();
            updateImportButtonState();
        }
    };

    elements.lastUploadChip?.addEventListener('click', dismissLastUpload);
    elements.lastUploadDismiss?.addEventListener('click', (event) => {
        event.stopPropagation();
        dismissLastUpload();
    });

    elements.closeButtons?.forEach((button) => button.addEventListener('click', () => root.classList.remove('open')));

    if (openOnError) root.classList.add('open');

    preview.showPlaceholder();
    updateImportButtonState();
    bindModalControls(root, root.id);
    handler?.bindPreviewEvents?.({ elements, state, preview, helpers: { escapeHtml, updateImportButtonState, clearFile } });
    handler?.bindFormExtras?.({
        elements,
        state,
        preview,
        helpers: { escapeHtml, updateImportButtonState, clearFile, expand, collapse },
        checkMonthUrl: root.dataset.checkMonthUrl || null,
        checkYearUrl: root.dataset.checkYearUrl || null,
    });
};