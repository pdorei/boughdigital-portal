import React from 'react';
import { Download, ExternalLink, FileText, Pencil, Trash2 } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import EmptyState from '@/components/ui/EmptyState';
import RowActions from '@/components/ui/RowActions';
import Table from '@/components/ui/Table';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { useWorkspace } from '@/hooks/useWorkspace';
import { formatBytes } from '@/utils/formatNumber';
import { optionLabel } from '@/utils/options';

export default function DocumentTable({ documents, base, showClient = true, onEdit, onDelete }) {
    const { formatDate } = useDateFormatter();
    const { options } = useWorkspace();

    if (!documents.length) {
        return <EmptyState icon={FileText} title="No documents" copy="Upload a file or link a Google Doc or other external document." />;
    }

    return (
        <Table
            columns={[
                {
                    key: 'title',
                    label: 'Document',
                    render: (document) => (
                        <a className="client-link" href={document.open_url} {...(document.download_url ? {} : { target: '_blank', rel: 'noopener noreferrer' })}>
                            {document.title}
                        </a>
                    ),
                },
                ...(showClient ? [{ key: 'client', label: 'Client', render: (document) => <a className="table-muted" href={`${base}/clients/${document.client?.slug}`}>{document.client?.name}</a> }] : []),
                { key: 'folder', label: 'Folder', render: (document) => optionLabel(options.document_folders, document.folder) },
                { key: 'source', label: 'Source', render: (document) => <Badge status={document.source === 'upload' ? 'ready' : 'default'}>{optionLabel(options.document_sources, document.source)}</Badge> },
                { key: 'size', label: 'Size', render: (document) => (document.download_url ? formatBytes(document.size_bytes) : '—') },
                { key: 'created_at', label: 'Added', render: (document) => formatDate(document.created_at) },
                {
                    key: 'actions',
                    label: 'Actions',
                    render: (document) => (
                        <RowActions
                            label={`Open actions for ${document.title}`}
                            actions={[
                                document.download_url
                                    ? { label: 'Download', icon: Download, href: document.download_url }
                                    : { label: 'Open link', icon: ExternalLink, href: document.external_url, external: true },
                                document.can?.update ? { label: 'Edit details', icon: Pencil, onClick: () => onEdit(document) } : null,
                                document.can?.delete ? {
                                    label: 'Delete',
                                    icon: Trash2,
                                    variant: 'danger',
                                    confirm: {
                                        title: `Delete "${document.title}"?`,
                                        description: document.download_url
                                            ? 'The document record and its uploaded file are removed permanently.'
                                            : 'The link is removed from the library. The external document itself is not changed.',
                                        confirmLabel: 'Delete document',
                                    },
                                    onClick: () => onDelete(document),
                                } : null,
                            ]}
                        />
                    ),
                },
            ]}
            rows={documents}
            rowKey={(document) => document.id}
        />
    );
}
