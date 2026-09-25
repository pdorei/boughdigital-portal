import React from 'react';
import { BriefcaseBusiness, Eye } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import RowActions from '@/components/ui/RowActions';
import AdminListPage from '@/pages/admin/components/AdminListPage';
import { getAdminClients } from '@/api/adminPlatform';

function workspaceOptions(pageData) {
    return [{ value: '', label: 'All Workspaces' }, ...(pageData.filter_workspaces || []).map((workspace) => ({ value: workspace.id, label: workspace.name }))];
}

export default function AdminClients() {
    return (
        <AdminListPage
            title="Clients"
            description="View client records across all workspaces with ownership, documents, and tasks visible."
            icon={BriefcaseBusiness}
            fetcher={getAdminClients}
            searchPlaceholder="Search clients"
            emptyTitle="No clients found"
            emptyCopy="Client records will appear here once workspaces create them."
            filters={[
                { name: 'workspace_id', label: 'Workspace', options: workspaceOptions },
                { name: 'status', label: 'Status', options: () => [{ value: '', label: 'All Statuses' }, { value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }, { value: 'on_hold', label: 'On Hold' }] },
            ]}
            columns={[
                { key: 'name', label: 'Client', render: (client) => client.name },
                { key: 'workspace', label: 'Workspace', render: (client) => client.workspace?.name || 'Platform' },
                { key: 'website_url', label: 'Primary Contact', render: (client) => client.website_url || 'Not set' },
                { key: 'documents_count', label: 'Documents' },
                { key: 'tasks_count', label: 'Tasks' },
                { key: 'status', label: 'Status', render: (client) => <Badge status={client.status}>{client.status || 'active'}</Badge> },
                {
                    key: 'actions',
                    label: 'Actions',
                    render: (client) => (
                        <RowActions
                            label={`Open actions for ${client.name}`}
                            actions={[
                                client.workspace ? { label: 'Open in workspace', icon: Eye, href: `/${client.workspace.slug}/clients/${client.slug}` } : null,
                            ]}
                        />
                    ),
                },
            ]}
        />
    );
}
