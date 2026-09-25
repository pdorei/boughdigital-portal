function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.__BOUGH_APP__?.csrf || '';
}

export class ApiError extends Error {
    constructor(message, response, payload = null) {
        super(message);
        this.name = 'ApiError';
        this.status = response?.status;
        this.payload = payload;
        this.errors = payload?.errors || {};
    }
}

export function readableApiError(status) {
    if (status === 401) return 'Your session has ended. Please sign in again.';
    if (status === 403) return 'You do not have permission to perform this action.';
    if (status === 404) return 'The requested record could not be found.';
    if (status === 419) return 'This page has expired. Refresh the page and try again.';
    if (status === 422) return 'Please review the highlighted fields.';
    if (status >= 500) return 'The server could not complete the request.';

    return 'The request could not be completed.';
}

export function queryString(params = {}) {
    const query = new URLSearchParams(
        Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined),
    );

    return query.toString() ? `?${query}` : '';
}

export function jsonBody(method, data) {
    return { method, body: JSON.stringify(data) };
}

export async function request(path, options = {}) {
    const isFormData = options.body instanceof FormData;
    const response = await fetch(path, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
            ...(options.headers || {}),
        },
    });

    const payload = response.status === 204 ? null : await response.json().catch(() => null);

    if (!response.ok) {
        // Validation messages from Laravel are specific; other statuses get a consistent message.
        const message = response.status === 422 && payload?.message ? payload.message : readableApiError(response.status);

        throw new ApiError(message, response, payload);
    }

    return payload;
}

export function workspaceRequest(workspaceSlug, path, options = {}) {
    return request(`/${workspaceSlug}/api${path}`, options);
}
