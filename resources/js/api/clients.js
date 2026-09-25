import { jsonBody, queryString, workspaceRequest } from '@/api/client';

export function getClients(workspaceSlug, params = {}) {
    return workspaceRequest(workspaceSlug, `/clients${queryString(params)}`);
}

export function createClient(workspaceSlug, data) {
    return workspaceRequest(workspaceSlug, '/clients', jsonBody('POST', data));
}

export function updateClient(workspaceSlug, clientId, data) {
    return workspaceRequest(workspaceSlug, `/clients/${clientId}`, jsonBody('PATCH', data));
}

export function deleteClient(workspaceSlug, clientId) {
    return workspaceRequest(workspaceSlug, `/clients/${clientId}`, { method: 'DELETE' });
}
