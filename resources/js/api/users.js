import { queryString, request, workspaceRequest } from '@/api/client';

export function getAdminUsers(params = {}) {
    return request(`/admin/api/users${queryString(params)}`);
}

export function createAdminUser(data) {
    return request('/admin/api/users', {
        body: JSON.stringify(data),
        method: 'POST',
    });
}

export function updateAdminUser(user, data) {
    return request(`/admin/api/users/${user}`, {
        body: JSON.stringify(data),
        method: 'PATCH',
    });
}

export function activateAdminUser(user) {
    return request(`/admin/api/users/${user}/activate`, { method: 'POST' });
}

export function deactivateAdminUser(user) {
    return request(`/admin/api/users/${user}/deactivate`, { method: 'POST' });
}

export function deleteAdminUser(user) {
    return request(`/admin/api/users/${user}`, { method: 'DELETE' });
}

export function getWorkspaceUsers(workspaceSlug, params = {}) {
    return workspaceRequest(workspaceSlug, `/users${queryString(params)}`);
}

export function createWorkspaceUser(workspaceSlug, data) {
    return workspaceRequest(workspaceSlug, '/users', {
        body: JSON.stringify(data),
        method: 'POST',
    });
}

export function updateWorkspaceUser(workspaceSlug, user, data) {
    return workspaceRequest(workspaceSlug, `/users/${user}`, {
        body: JSON.stringify(data),
        method: 'PATCH',
    });
}

export function activateWorkspaceUser(workspaceSlug, user) {
    return workspaceRequest(workspaceSlug, `/users/${user}/activate`, { method: 'POST' });
}

export function deactivateWorkspaceUser(workspaceSlug, user) {
    return workspaceRequest(workspaceSlug, `/users/${user}/deactivate`, { method: 'POST' });
}

export function removeWorkspaceMember(workspaceSlug, user) {
    return workspaceRequest(workspaceSlug, `/users/${user}/membership`, { method: 'DELETE' });
}
