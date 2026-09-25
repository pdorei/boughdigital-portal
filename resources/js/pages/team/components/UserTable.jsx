import React from 'react';
import { CircleCheck, CircleX, Eye, Pencil, Trash2, UserMinus, Users } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import EmptyState from '@/components/ui/EmptyState';
import RowActions from '@/components/ui/RowActions';
import Table from '@/components/ui/Table';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import RoleBadge from '@/pages/team/components/RoleBadge';

export default function UserTable({ users, base, showWorkspace = false, onToggle, onRemoveFromWorkspace, onDelete }) {
    const { formatDateTime } = useDateFormatter();

    if (!users.length) {
        return <EmptyState icon={Users} title="No users found" copy="Try a different search or filter." />;
    }

    return (
        <Table
            columns={[
                { key: 'name', label: 'Name', render: (user) => <a className="client-link" href={`${base}/${user.id}`}>{user.name}</a> },
                { key: 'email', label: 'Email' },
                ...(showWorkspace ? [{ key: 'workspace', label: 'Workspace', render: (user) => user.workspace?.name || 'Platform' }] : []),
                { key: 'role', label: 'Role', cellClassName: 'table-role-cell', render: (user) => <RoleBadge role={user.role} /> },
                { key: 'is_active', label: 'Status', render: (user) => <Badge status={user.is_active ? 'active' : 'on_hold'}>{user.is_active ? 'Active' : 'Inactive'}</Badge> },
                { key: 'last_login', label: 'Last sign-in', render: (user) => formatDateTime(user.last_login, 'Never') },
                {
                    key: 'actions',
                    label: 'Actions',
                    render: (user) => (
                        <RowActions
                            label={`Open actions for ${user.name}`}
                            actions={[
                                { label: 'View', icon: Eye, href: `${base}/${user.id}` },
                                user.can?.update ? { label: 'Edit', icon: Pencil, href: `${base}/${user.id}/edit` } : null,
                                user.can?.deactivate && !user.is_active ? { label: 'Activate', icon: CircleCheck, onClick: () => onToggle(user) } : null,
                                user.can?.deactivate && user.is_active ? {
                                    label: 'Deactivate',
                                    icon: CircleX,
                                    variant: 'danger',
                                    confirm: {
                                        title: `Deactivate ${user.name}?`,
                                        description: 'They are signed out immediately and cannot sign in until reactivated. Their work and history are kept.',
                                        confirmLabel: 'Deactivate user',
                                    },
                                    onClick: () => onToggle(user),
                                } : null,
                                onRemoveFromWorkspace && user.can?.remove_from_workspace ? {
                                    label: 'Remove from workspace',
                                    icon: UserMinus,
                                    variant: 'danger',
                                    confirm: {
                                        title: `Remove ${user.name} from this workspace?`,
                                        description: 'They lose access to this workspace and its clients. Their platform account, tasks, documents and activity history are kept.',
                                        confirmLabel: 'Remove from workspace',
                                    },
                                    onClick: () => onRemoveFromWorkspace(user),
                                } : null,
                                onDelete && user.can?.delete ? {
                                    label: 'Delete user',
                                    icon: Trash2,
                                    variant: 'danger',
                                    confirm: {
                                        title: `Permanently delete ${user.name}?`,
                                        description: 'The platform account is deleted. Tasks and documents they owned are kept but show no owner. Users with logged time cannot be deleted; deactivate them instead.',
                                        confirmLabel: 'Delete user',
                                    },
                                    onClick: () => onDelete(user),
                                } : null,
                            ]}
                        />
                    ),
                },
            ]}
            rows={users}
            rowKey={(user) => user.id}
        />
    );
}
