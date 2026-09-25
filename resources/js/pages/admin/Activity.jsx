import React from 'react';
import { Activity } from 'lucide-react';
import AdminListPage from '@/pages/admin/components/AdminListPage';
import { getAdminActivity } from '@/api/adminPlatform';
import { useDateFormatter } from '@/hooks/useDateFormatter';

function workspaceOptions(pageData) {
    return [{ value: '', label: 'All Workspaces' }, ...(pageData.filter_workspaces || []).map((workspace) => ({ value: workspace.id, label: workspace.name }))];
}

function userOptions(pageData) {
    return [{ value: '', label: 'All Users' }, ...(pageData.filter_users || []).map((user) => ({ value: user.id, label: user.name }))];
}

export default function AdminActivity() {
    const { formatDateTime } = useDateFormatter();

    return (
        <AdminListPage
            title="Activity"
            description="Inspect platform and workspace activity without exposing sensitive payloads."
            icon={Activity}
            fetcher={getAdminActivity}
            searchPlaceholder="Filter actions"
            emptyTitle="No activity found"
            emptyCopy="Activity records will appear here as users work in the portal."
            filters={[
                { name: 'workspace_id', label: 'Workspace', options: workspaceOptions },
                { name: 'user_id', label: 'User', options: userOptions },
            ]}
            columns={[
                { key: 'created_at', label: 'Timestamp', render: (activity) => formatDateTime(activity.created_at, 'Recently') },
                { key: 'workspace', label: 'Workspace', render: (activity) => activity.workspace?.name || 'Platform' },
                { key: 'user', label: 'User', render: (activity) => activity.user?.name || 'System' },
                { key: 'action', label: 'Action' },
                { key: 'entity_type', label: 'Resource Type', render: (activity) => activity.entity_type || 'Not set' },
                { key: 'entity_id', label: 'Resource', render: (activity) => activity.entity_id || 'Not set' },
                { key: 'source', label: 'Status', render: (activity) => activity.source || 'portal' },
                { key: 'ip_address', label: 'IP', render: (activity) => activity.ip_address || 'Not recorded' },
            ]}
        />
    );
}
