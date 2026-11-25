import React from 'react';
import { CalendarDays, Eye, FilePlus2, Pencil, Trash2 } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import EmptyState from '@/components/ui/EmptyState';
import RowActions from '@/components/ui/RowActions';
import Table from '@/components/ui/Table';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { useWorkspace } from '@/hooks/useWorkspace';
import { optionLabel } from '@/utils/options';
import PillarName from '@/pages/clients/components/content/PillarName';

export function calendarActions(entry, { articleUrl, onView, onEdit, onDelete, onCreateArticle, canCreateArticle }) {
    return [
        { label: 'View', icon: Eye, onClick: () => onView(entry) },
        entry.can?.update ? { label: 'Edit', icon: Pencil, onClick: () => onEdit(entry) } : null,
        entry.article ? { label: 'Open article', icon: FilePlus2, href: articleUrl(entry.article.id) } : null,
        !entry.article && canCreateArticle ? { label: 'Create article', icon: FilePlus2, onClick: () => onCreateArticle(entry) } : null,
        entry.can?.delete ? {
            label: 'Delete',
            icon: Trash2,
            variant: 'danger',
            confirm: {
                title: `Delete "${entry.title}"?`,
                description: entry.article ? 'The calendar entry is removed. Its linked article is kept.' : 'The calendar entry is removed permanently.',
                confirmLabel: 'Delete entry',
            },
            onClick: () => onDelete(entry),
        } : null,
    ];
}

export default function CalendarTable({ entries, actions, onView }) {
    const { formatDate } = useDateFormatter();
    const { options } = useWorkspace();

    if (!entries.length) {
        return <EmptyState icon={CalendarDays} title="Nothing planned" copy="Add the articles and pages this client should publish, with keywords, writers and dates." />;
    }

    return (
        <Table
            columns={[
                {
                    key: 'title',
                    label: 'Title',
                    render: (entry) => (
                        <span className="grid">
                            <button type="button" className="client-link link-button" onClick={() => onView(entry)}>{entry.title}</button>
                            {entry.target_keyword ? <span className="table-muted">{entry.target_keyword}</span> : null}
                        </span>
                    ),
                },
                { key: 'publish_date', label: 'Publish', render: (entry) => formatDate(entry.publish_date, 'Not scheduled') },
                { key: 'pillar', label: 'Pillar', render: (entry) => <PillarName pillar={entry.pillar} /> },
                { key: 'writer', label: 'Writer', render: (entry) => entry.writer?.name || <span className="table-muted">Unassigned</span> },
                { key: 'status', label: 'Status', render: (entry) => <Badge status={entry.status}>{optionLabel(options.content_calendar_statuses, entry.status)}</Badge> },
                { key: 'actions', label: 'Actions', render: (entry) => <RowActions label={`Open actions for ${entry.title}`} actions={actions(entry)} /> },
            ]}
            rows={entries}
            rowKey={(entry) => entry.id}
        />
    );
}
