import { jsonBody, workspaceRequest } from '@/api/client';

export function getClientAiConfig(workspaceSlug, clientId) {
    return workspaceRequest(workspaceSlug, `/clients/${clientId}/ai-config`);
}

export function saveClientAiConfig(workspaceSlug, clientId, data) {
    return workspaceRequest(workspaceSlug, `/clients/${clientId}/ai-config`, jsonBody('PUT', data));
}

export function resetClientAiConfig(workspaceSlug, clientId) {
    return workspaceRequest(workspaceSlug, `/clients/${clientId}/ai-config`, { method: 'DELETE' });
}
