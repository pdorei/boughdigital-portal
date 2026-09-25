import React from 'react';
import { BriefcaseBusiness, CircleCheck, CircleX, Eye, Pencil } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import EmptyState from '@/components/ui/EmptyState';
import RowActions from '@/components/ui/RowActions';
import Table from '@/components/ui/Table';
import { useDateFormatter } from '@/hooks/useDateFormatter';

export default function WorkspaceTable({ workspaces, onToggle }) {
    const { formatDateTime } = useDateFormatter();

    if (!workspaces.length) {
        return <EmptyState icon={BriefcaseBusiness} title="No workspaces found" copy="Create a workspace to start managing users, clients and delivery." />;
    }

    return (
        <Table
            columns={[
                { key: 'name', label: 'Workspace', render: (workspace) => <a className="client-link" href={`/admin/workspaces/${workspace.id}`}>{workspace.name}</a> },
                { key: 'slug', label: 'Slug' },
                { key: 'users_count', label: 'Users' },
                { key: 'clients_count', label: 'Clients' },
                { key: 'is_active', label: 'Status', render: (workspace) => <Badge status={workspace.is_active ? 'active' : 'on_hold'}>{workspace.is_active ? 'Active' : 'Inactive'}</Badge> },
                { key: 'created_at', label: 'Created', render: (workspace) => formatDateTime(workspace.created_at) },
                {
                    key: 'actions',
                    label: 'Actions',
                    render: (workspace) => (
                        <RowActions
                            label={`Open actions for ${workspace.name}`}
                            actions={[
                                { label: 'View', icon: Eye, href: `/admin/workspaces/${workspace.id}` },
                                { label: 'Edit', icon: Pencil, href: `/admin/workspaces/${workspace.id}/edit` },
                                workspace.is_active ? {
                                    label: 'Deactivate',
                                    icon: CircleX,
                                    variant: 'danger',
                                    confirm: {
                                        title: `Deactivate ${workspace.name}?`,
                                        description: 'Members can no longer open the workspace and its MCP endpoint stops responding. All data is kept and the workspace can be reactivated.',
                                        confirmLabel: 'Deactivate workspace',
                                    },
                                    onClick: () => onToggle(workspace),
                                } : { label: 'Activate', icon: CircleCheck, onClick: () => onToggle(workspace) },
                            ]}
                        />
                    ),
                },
            ]}
            rows={workspaces}
            rowKey={(workspace) => workspace.id}
        />
    );
}
