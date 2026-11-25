import { jsonBody, workspaceRequest } from '@/api/client';

function integrationsPath(clientId, integrationId = null) {
    return `/clients/${clientId}/integrations${integrationId ? `/${integrationId}` : ''}`;
}

export function getIntegrations(workspaceSlug, clientId) {
    return workspaceRequest(workspaceSlug, integrationsPath(clientId));
}

export function createIntegration(workspaceSlug, clientId, data) {
    return workspaceRequest(workspaceSlug, integrationsPath(clientId), jsonBody('POST', data));
}

export function updateIntegration(workspaceSlug, clientId, integrationId, data) {
    return workspaceRequest(workspaceSlug, integrationsPath(clientId, integrationId), jsonBody('PATCH', data));
}

export function disconnectIntegration(workspaceSlug, clientId, integrationId) {
    return workspaceRequest(workspaceSlug, `${integrationsPath(clientId, integrationId)}/disconnect`, { method: 'POST' });
}

export function deleteIntegration(workspaceSlug, clientId, integrationId) {
    return workspaceRequest(workspaceSlug, integrationsPath(clientId, integrationId), { method: 'DELETE' });
}
