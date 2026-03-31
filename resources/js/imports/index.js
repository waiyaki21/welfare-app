import { initImportModal } from './core';
import { createYearHandler } from './handlers/year';
import { createMonthlyHandler } from './handlers/monthly';
import { createExpenditureHandler } from './handlers/expenditure';

const handlers = {
    year: createYearHandler(),
    monthly: createMonthlyHandler(),
    expenditure: createExpenditureHandler(),
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-import-modal]').forEach((root) => {
        const type = root.dataset.importType;
        const tabs = (root.dataset.tabs || '')
            .split(',')
            .map((tab) => tab.trim())
            .filter(Boolean);

        initImportModal({
            root,
            previewUrl: root.dataset.previewUrl,
            finalUrl: root.dataset.finalUrl,
            lastUploadUrl: root.dataset.lastUploadUrl,
            tabs: tabs.length ? tabs : ['summary', 'errors'],
            handler: handlers[type],
            openOnError: root.dataset.openOnError === '1',
        });
    });
});
