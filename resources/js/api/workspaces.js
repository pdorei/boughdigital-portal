import { queryString, request } from '@/api/client';

export function getWorkspaces(params = {}) {
    return request(`/admin/api/workspaces${queryString(params)}`);
}

export function createWorkspace(data) {
    if (data instanceof FormData) {
        return request('/admin/api/workspaces', {
            body: data,
            method: 'POST',
        });
    }

    return request('/admin/api/workspaces', {
        body: JSON.stringify(data),
        method: 'POST',
    });
}

export function getWorkspace(workspace) {
    return request(`/admin/api/workspaces/${workspace}`);
}

export function updateWorkspace(workspace, data) {
    if (data instanceof FormData) {
        data.append('_method', 'PATCH');

        return request(`/admin/api/workspaces/${workspace}`, {
            body: data,
            method: 'POST',
        });
    }

    return request(`/admin/api/workspaces/${workspace}`, {
        body: JSON.stringify(data),
        method: 'PATCH',
    });
}

export function activateWorkspace(workspace) {
    return request(`/admin/api/workspaces/${workspace}/activate`, { method: 'POST' });
}

export function deactivateWorkspace(workspace) {
    return request(`/admin/api/workspaces/${workspace}/deactivate`, { method: 'POST' });
}

export function deleteWorkspace(workspace, confirmation) {
    return request(`/admin/api/workspaces/${workspace}`, {
        body: JSON.stringify({ confirmation }),
        method: 'DELETE',
    });
}
