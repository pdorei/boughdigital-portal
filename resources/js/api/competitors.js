import { jsonBody, workspaceRequest } from '@/api/client';

function competitorsPath(clientId, competitorId = null) {
    return `/clients/${clientId}/competitors${competitorId ? `/${competitorId}` : ''}`;
}

export function getCompetitors(workspaceSlug, clientId) {
    return workspaceRequest(workspaceSlug, competitorsPath(clientId));
}

export function createCompetitor(workspaceSlug, clientId, data) {
    return workspaceRequest(workspaceSlug, competitorsPath(clientId), jsonBody('POST', data));
}

export function updateCompetitor(workspaceSlug, clientId, competitorId, data) {
    return workspaceRequest(workspaceSlug, competitorsPath(clientId, competitorId), jsonBody('PATCH', data));
}

export function deleteCompetitor(workspaceSlug, clientId, competitorId) {
    return workspaceRequest(workspaceSlug, competitorsPath(clientId, competitorId), { method: 'DELETE' });
}

export function uploadCompetitorLogo(workspaceSlug, clientId, competitorId, file) {
    const body = new FormData();
    body.append('logo', file);

    return workspaceRequest(workspaceSlug, `${competitorsPath(clientId, competitorId)}/logo`, { method: 'POST', body });
}

export function removeCompetitorLogo(workspaceSlug, clientId, competitorId) {
    return workspaceRequest(workspaceSlug, `${competitorsPath(clientId, competitorId)}/logo`, { method: 'DELETE' });
}
