import { jsonBody, queryString, workspaceRequest } from '@/api/client';

export function getTasks(workspaceSlug, params = {}) {
    return workspaceRequest(workspaceSlug, `/tasks${queryString(params)}`);
}

export function getTask(workspaceSlug, taskId) {
    return workspaceRequest(workspaceSlug, `/tasks/${taskId}`);
}

export function createTask(workspaceSlug, data) {
    return workspaceRequest(workspaceSlug, '/tasks', jsonBody('POST', data));
}

export function updateTask(workspaceSlug, taskId, data) {
    return workspaceRequest(workspaceSlug, `/tasks/${taskId}`, jsonBody('PATCH', data));
}

export function updateTaskStatus(workspaceSlug, taskId, status) {
    return workspaceRequest(workspaceSlug, `/tasks/${taskId}/status`, jsonBody('PATCH', { status }));
}

export function deleteTask(workspaceSlug, taskId) {
    return workspaceRequest(workspaceSlug, `/tasks/${taskId}`, { method: 'DELETE' });
}
