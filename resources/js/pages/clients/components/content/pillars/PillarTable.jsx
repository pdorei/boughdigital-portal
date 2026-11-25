import React from 'react';
import { Layers, Pencil, Power, Trash2 } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import EmptyState from '@/components/ui/EmptyState';
import RowActions from '@/components/ui/RowActions';
import Table from '@/components/ui/Table';
import PillarName from '@/pages/clients/components/content/PillarName';

export default function PillarTable({ pillars, onEdit, onToggle, onDelete }) {
    if (!pillars.length) {
        return <EmptyState icon={Layers} title="No content pillars yet" copy="Pillars are the main topics this client publishes about, such as Tax or Small Business." />;
    }

    return (
        <Table
            columns={[
                { key: 'name', label: 'Name', render: (pillar) => <PillarName pillar={pillar} strong /> },
                { key: 'description', label: 'Description', render: (pillar) => pillar.description || <span className="table-muted">—</span> },
                { key: 'articles', label: 'Articles', render: (pillar) => pillar.articles_count },
                { key: 'status', label: 'Status', render: (pillar) => <Badge status={pillar.active ? 'active' : 'default'}>{pillar.active ? 'Active' : 'Inactive'}</Badge> },
                {
                    key: 'actions',
                    label: 'Actions',
                    render: (pillar) => (
                        <RowActions
                            label={`Open actions for ${pillar.name}`}
                            actions={[
                                pillar.can?.update ? { label: 'Edit', icon: Pencil, onClick: () => onEdit(pillar) } : null,
                                pillar.can?.update ? { label: pillar.active ? 'Deactivate' : 'Activate', icon: Power, onClick: () => onToggle(pillar) } : null,
                                pillar.can?.delete ? {
                                    label: 'Delete',
                                    icon: Trash2,
                                    variant: 'danger',
                                    confirm: {
                                        title: `Delete "${pillar.name}"?`,
                                        description: pillar.articles_count || pillar.calendar_entries_count
                                            ? 'This pillar is used by articles or calendar entries, so it cannot be deleted. Deactivate it instead.'
                                            : 'The pillar is removed permanently.',
                                        confirmLabel: 'Delete pillar',
                                    },
                                    onClick: () => onDelete(pillar),
                                } : null,
                            ]}
                        />
                    ),
                },
            ]}
            rows={pillars}
            rowKey={(pillar) => pillar.id}
        />
    );
}
