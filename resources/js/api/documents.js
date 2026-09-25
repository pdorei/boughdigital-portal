import { jsonBody, queryString, workspaceRequest } from '@/api/client';

export function getDocuments(workspaceSlug, params = {}) {
    return workspaceRequest(workspaceSlug, `/documents${queryString(params)}`);
}

export function uploadDocument(workspaceSlug, formData) {
    formData.set('kind', 'upload');

    return workspaceRequest(workspaceSlug, '/documents', { method: 'POST', body: formData });
}

export function linkDocument(workspaceSlug, data) {
    return workspaceRequest(workspaceSlug, '/documents', jsonBody('POST', { ...data, kind: 'link' }));
}

export function updateDocument(workspaceSlug, documentId, data) {
    return workspaceRequest(workspaceSlug, `/documents/${documentId}`, jsonBody('PATCH', data));
}

export function deleteDocument(workspaceSlug, documentId) {
    return workspaceRequest(workspaceSlug, `/documents/${documentId}`, { method: 'DELETE' });
}
