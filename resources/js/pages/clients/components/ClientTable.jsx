import React from 'react';
import { Archive, ArchiveRestore, BriefcaseBusiness, Eye, Pencil, Trash2 } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import EmptyState from '@/components/ui/EmptyState';
import RowActions from '@/components/ui/RowActions';
import Table from '@/components/ui/Table';

export default function ClientTable({ clients, base, onArchive, onRestore, onDelete }) {
    if (!clients.length) {
        return <EmptyState icon={BriefcaseBusiness} title="No clients found" copy="Try a different search or filter, or add a new client." />;
    }

    return (
        <Table
            columns={[
                { key: 'name', label: 'Client', render: (client) => <a className="client-link" href={`${base}/clients/${client.slug}`}>{client.name}</a> },
                { key: 'website_url', label: 'Website', render: (client) => <span className="table-muted">{client.website_url || 'Not set'}</span> },
                { key: 'status', label: 'Status', render: (client) => <Badge status={client.status}>{client.status}</Badge> },
                { key: 'open_tasks_count', label: 'Open tasks' },
                { key: 'documents_count', label: 'Documents' },
                {
                    key: 'actions',
                    label: 'Actions',
                    render: (client) => (
                        <RowActions
                            label={`Open actions for ${client.name}`}
                            actions={[
                                { label: 'View', icon: Eye, href: `${base}/clients/${client.slug}` },
                                client.can?.update ? { label: 'Edit', icon: Pencil, href: `${base}/clients/${client.slug}/edit` } : null,
                                client.can?.update && client.status !== 'archived' ? {
                                    label: 'Archive',
                                    icon: Archive,
                                    confirm: {
                                        title: `Archive ${client.name}?`,
                                        description: 'The client is hidden from active lists. Its tasks, documents and history are kept and it can be restored at any time.',
                                        confirmLabel: 'Archive client',
                                    },
                                    onClick: () => onArchive(client),
                                } : null,
                                client.can?.update && client.status === 'archived' ? { label: 'Restore', icon: ArchiveRestore, onClick: () => onRestore(client) } : null,
                                client.can?.delete ? {
                                    label: 'Delete',
                                    icon: Trash2,
                                    variant: 'danger',
                                    confirm: {
                                        title: `Delete ${client.name}?`,
                                        description: 'This permanently removes the client. Clients with tasks or documents cannot be deleted; archive them instead.',
                                        confirmLabel: 'Delete client',
                                    },
                                    onClick: () => onDelete(client),
                                } : null,
                            ]}
                        />
                    ),
                },
            ]}
            rows={clients}
            rowKey={(client) => client.id}
        />
    );
}
