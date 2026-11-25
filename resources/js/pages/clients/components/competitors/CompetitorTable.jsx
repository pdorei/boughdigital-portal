import React from 'react';
import { Eye, Pencil, Swords, Trash2 } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import EmptyState from '@/components/ui/EmptyState';
import RowActions from '@/components/ui/RowActions';
import Table from '@/components/ui/Table';
import { useWorkspace } from '@/hooks/useWorkspace';
import { optionLabel } from '@/utils/options';

export default function CompetitorTable({ competitors, onView, onEdit, onDelete }) {
    const { options } = useWorkspace();

    if (!competitors.length) {
        return <EmptyState icon={Swords} title="No competitors yet" copy="Add the businesses this client competes with for customers and search visibility." />;
    }

    return (
        <Table
            columns={[
                {
                    key: 'name',
                    label: 'Name',
                    render: (competitor) => (
                        <span className="competitor-name">
                            {competitor.logo_url ? <img className="competitor-logo" src={competitor.logo_url} alt="" /> : null}
                            <button type="button" className="client-link link-button" onClick={() => onView(competitor)}>{competitor.competitor_name}</button>
                        </span>
                    ),
                },
                {
                    key: 'domain',
                    label: 'Domain',
                    render: (competitor) => <a className="table-muted" href={competitor.website_url} target="_blank" rel="noopener noreferrer">{competitor.competitor_domain}</a>,
                },
                { key: 'type', label: 'Type', render: (competitor) => (competitor.competitor_type ? optionLabel(options.competitor_types, competitor.competitor_type) : <span className="table-muted">—</span>) },
                {
                    key: 'tier',
                    label: 'Tier',
                    render: (competitor) => <Badge status={competitor.tier === 'primary' ? 'active' : 'default'}>{optionLabel(options.competitor_tiers, competitor.tier)}</Badge>,
                },
                { key: 'industry', label: 'Industry', render: (competitor) => competitor.industry || <span className="table-muted">—</span> },
                { key: 'location', label: 'Location', render: (competitor) => competitor.location || <span className="table-muted">—</span> },
                {
                    key: 'actions',
                    label: 'Actions',
                    render: (competitor) => (
                        <RowActions
                            label={`Open actions for ${competitor.competitor_name}`}
                            actions={[
                                { label: 'View', icon: Eye, onClick: () => onView(competitor) },
                                competitor.can?.update ? { label: 'Edit', icon: Pencil, onClick: () => onEdit(competitor) } : null,
                                competitor.can?.delete ? {
                                    label: 'Delete',
                                    icon: Trash2,
                                    variant: 'danger',
                                    confirm: {
                                        title: `Delete "${competitor.competitor_name}"?`,
                                        description: 'The competitor and its logo are removed from this client permanently.',
                                        confirmLabel: 'Delete competitor',
                                    },
                                    onClick: () => onDelete(competitor),
                                } : null,
                            ]}
                        />
                    ),
                },
            ]}
            rows={competitors}
            rowKey={(competitor) => competitor.id}
        />
    );
}
