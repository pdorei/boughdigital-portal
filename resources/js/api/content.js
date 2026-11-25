import { jsonBody, queryString, workspaceRequest } from '@/api/client';

const clientPath = (clientId, path) => `/clients/${clientId}${path}`;

export function getPillars(workspaceSlug, clientId) {
    return workspaceRequest(workspaceSlug, clientPath(clientId, '/content-pillars'));
}

export function createPillar(workspaceSlug, clientId, data) {
    return workspaceRequest(workspaceSlug, clientPath(clientId, '/content-pillars'), jsonBody('POST', data));
}

export function updatePillar(workspaceSlug, clientId, pillarId, data) {
    return workspaceRequest(workspaceSlug, clientPath(clientId, `/content-pillars/${pillarId}`), jsonBody('PATCH', data));
}

export function deletePillar(workspaceSlug, clientId, pillarId) {
    return workspaceRequest(workspaceSlug, clientPath(clientId, `/content-pillars/${pillarId}`), { method: 'DELETE' });
}

export function getCalendarEntries(workspaceSlug, clientId, params = {}) {
    return workspaceRequest(workspaceSlug, clientPath(clientId, `/content-calendar${queryString(params)}`));
}

export function createCalendarEntry(workspaceSlug, clientId, data) {
    return workspaceRequest(workspaceSlug, clientPath(clientId, '/content-calendar'), jsonBody('POST', data));
}

export function updateCalendarEntry(workspaceSlug, clientId, entryId, data) {
    return workspaceRequest(workspaceSlug, clientPath(clientId, `/content-calendar/${entryId}`), jsonBody('PATCH', data));
}

export function deleteCalendarEntry(workspaceSlug, clientId, entryId) {
    return workspaceRequest(workspaceSlug, clientPath(clientId, `/content-calendar/${entryId}`), { method: 'DELETE' });
}

export function getArticles(workspaceSlug, clientId, params = {}) {
    return workspaceRequest(workspaceSlug, clientPath(clientId, `/articles${queryString(params)}`));
}

export function createArticle(workspaceSlug, clientId, data) {
    return workspaceRequest(workspaceSlug, clientPath(clientId, '/articles'), jsonBody('POST', data));
}

export function updateArticle(workspaceSlug, clientId, articleId, data) {
    return workspaceRequest(workspaceSlug, clientPath(clientId, `/articles/${articleId}`), jsonBody('PATCH', data));
}

export function deleteArticle(workspaceSlug, clientId, articleId) {
    return workspaceRequest(workspaceSlug, clientPath(clientId, `/articles/${articleId}`), { method: 'DELETE' });
}

export function getArticleScores(workspaceSlug, clientId, articleId) {
    return workspaceRequest(workspaceSlug, clientPath(clientId, `/articles/${articleId}/scores`));
}

export function addArticleScore(workspaceSlug, clientId, articleId, data) {
    return workspaceRequest(workspaceSlug, clientPath(clientId, `/articles/${articleId}/scores`), jsonBody('POST', data));
}
