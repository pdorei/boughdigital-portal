import { jsonBody, request } from '@/api/client';

// Platform connections return configured flags only; keys and tokens never reach the browser.
export function getConnections() {
    return request('/admin/api/connections');
}

export function updateClaudeConnection(data) {
    return request('/admin/api/connections/anthropic', jsonBody('PATCH', data));
}

export function disconnectConnection(provider) {
    return request(`/admin/api/connections/${provider}/disconnect`, { method: 'POST' });
}

export const UPWORK_CONNECT_URL = '/admin/upwork/oauth/connect';

export function getUpworkSettings() {
    return request('/admin/api/settings/upwork');
}

export function updateUpworkSettings(data) {
    return request('/admin/api/settings/upwork', jsonBody('PATCH', data));
}
