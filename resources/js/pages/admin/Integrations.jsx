import React from 'react';
import { ShieldCheck } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import AdminListPage from '@/pages/admin/components/AdminListPage';
import { getAdminIntegrations } from '@/api/adminPlatform';
import { useDateFormatter } from '@/hooks/useDateFormatter';

function workspaceOptions(pageData) {
    return [{ value: '', label: 'All Workspaces' }, ...(pageData.filter_workspaces || []).map((workspace) => ({ value: workspace.id, label: workspace.name }))];
}

function clientOptions(pageData) {
    return [{ value: '', label: 'All Clients' }, ...(pageData.filter_clients || []).map((client) => ({ value: client.id, label: `${client.name}${client.workspace ? ` / ${client.workspace}` : ''}` }))];
}

export default function AdminIntegrations() {
    const { formatDateTime } = useDateFormatter();

    return (
        <AdminListPage
            title="Integrations"
            description="Review external service connections by workspace and client."
            icon={ShieldCheck}
            fetcher={getAdminIntegrations}
            searchPlaceholder="Search providers"
            emptyTitle="No integrations found"
            emptyCopy="Client integration records will appear here once connected."
            filters={[
                { name: 'workspace_id', label: 'Workspace', options: workspaceOptions },
                { name: 'client_id', label: 'Client', options: clientOptions },
                { name: 'status', label: 'Status', options: () => [{ value: '', label: 'All Statuses' }, { value: 'connected', label: 'Connected' }, { value: 'disconnected', label: 'Disconnected' }, { value: 'error', label: 'Error' }, { value: 'expired', label: 'Expired' }] },
            ]}
            columns={[
                { key: 'provider', label: 'Provider' },
                { key: 'workspace', label: 'Workspace', render: (integration) => integration.workspace?.name || 'Platform' },
                { key: 'client', label: 'Client', render: (integration) => integration.client?.name || 'No client' },
                { key: 'status', label: 'Status', render: (integration) => <Badge status={integration.status}>{integration.status}</Badge> },
                { key: 'last_verified_at', label: 'Last Verified', render: (integration) => formatDateTime(integration.last_verified_at, 'Never') },
                { key: 'updated_at', label: 'Updated', render: (integration) => formatDateTime(integration.updated_at, 'Recently') },
            ]}
        />
    );
}
