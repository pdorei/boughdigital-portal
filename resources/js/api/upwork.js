import { jsonBody, queryString, request } from '@/api/client';

const BASE = '/admin/api/upwork';

export function getUpworkOverview() {
    return request(`${BASE}/overview`);
}

export function startUpworkRun() {
    return request(`${BASE}/runs`, { method: 'POST' });
}

export function getUpworkJobs(params = {}) {
    return request(`${BASE}/jobs${queryString(params)}`);
}

export function getUpworkJob(id) {
    return request(`${BASE}/jobs/${id}`);
}

export function reviewUpworkJob(id, decision, reason = null) {
    return request(`${BASE}/jobs/${id}/review`, jsonBody('POST', { decision, reason }));
}

export function sendUpworkJobToReview(id) {
    return request(`${BASE}/jobs/${id}/send-to-review`, { method: 'POST' });
}

export function draftUpworkProposal(id) {
    return request(`${BASE}/jobs/${id}/draft-proposal`, { method: 'POST' });
}

export function getUpworkProposals(params = {}) {
    return request(`${BASE}/proposals${queryString(params)}`);
}

export function getUpworkProposal(id) {
    return request(`${BASE}/proposals/${id}`);
}

export function updateUpworkProposal(id, data) {
    return request(`${BASE}/proposals/${id}`, jsonBody('PATCH', data));
}

export function approveUpworkProposal(id) {
    return request(`${BASE}/proposals/${id}/approve`, { method: 'POST' });
}

export function rejectUpworkProposal(id, reason = null) {
    return request(`${BASE}/proposals/${id}/reject`, jsonBody('POST', { reason }));
}

export function markUpworkProposalSent(id, connectsSpent = null) {
    return request(`${BASE}/proposals/${id}/sent`, jsonBody('POST', { connects_spent: connectsSpent }));
}
