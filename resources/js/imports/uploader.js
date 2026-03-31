export const postForm = async (url, formData, csrfToken) => {
    if (!url) {
        throw new Error('Missing upload URL');
    }

    try {
        const response = await window.axios.post(url, formData, {
            headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
        });
        return response.data;
    } catch (error) {
        if (error?.response) {
            const data = error.response.data || {};
            const wrapped = new Error(data.message || 'Request failed');
            wrapped.status = error.response.status;
            wrapped.data = data;
            throw wrapped;
        }
        throw error;
    }
};
