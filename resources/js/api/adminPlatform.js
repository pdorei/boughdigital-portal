import { queryString, request } from '@/api/client';

export function getAdminClients(params = {}) {
    return request(`/admin/api/clients${queryString(params)}`);
}

export function getAdminDocuments(params = {}) {
    return request(`/admin/api/documents${queryString(params)}`);
}

export function getAdminTasks(params = {}) {
    return request(`/admin/api/tasks${queryString(params)}`);
}

export function getAdminIntegrations(params = {}) {
    return request(`/admin/api/integrations${queryString(params)}`);
}

export function getAdminMcp(params = {}) {
    return request(`/admin/api/mcp${queryString(params)}`);
}

export function getAdminActivity(params = {}) {
    return request(`/admin/api/activity${queryString(params)}`);
}
