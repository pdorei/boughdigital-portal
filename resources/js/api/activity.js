import { queryString, workspaceRequest } from '@/api/client';

export function getActivity(workspaceSlug, params = {}) {
    return workspaceRequest(workspaceSlug, `/activity${queryString(params)}`);
}
