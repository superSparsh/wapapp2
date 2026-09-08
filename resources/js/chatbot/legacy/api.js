export function getBuilderConfig() {
    const root = document.getElementById('chatbot-react-root');

    if (window.__CHATBOT_BUILDER_CONFIG__) {
        return window.__CHATBOT_BUILDER_CONFIG__;
    }

    return {
        flowId: root?.dataset.flowId || '',
        flowUuid: root?.dataset.flowUuid || '',
        builderDataUrl: root?.dataset.builderDataUrl || '',
        saveUrl: root?.dataset.saveUrl || '',
        clearCacheUrl: root?.dataset.clearCacheUrl || '',
        toggleUrl: root?.dataset.toggleUrl || '',
        isActive: root?.dataset.isActive === '1',
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '',
    };
}

export function applyAxiosDefaults(axios) {
    const config = getBuilderConfig();

    if (config.csrfToken) {
        axios.defaults.headers.common['X-CSRF-TOKEN'] = config.csrfToken;
    }

    axios.defaults.headers.common['Accept'] = 'application/json';
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
}
