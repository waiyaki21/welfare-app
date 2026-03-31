export const createBaseState = () => ({
    previewReady: false,
    previewData: null,
    activeTab: 'summary',
    selectedFile: null,
    usingLastUpload: false,
    hasErrors: false,
});

export const resetBaseState = (state) => {
    state.previewReady = false;
    state.previewData = null;
    state.activeTab = 'summary';
    state.selectedFile = null;
    state.usingLastUpload = false;
    state.hasErrors = false;
};
