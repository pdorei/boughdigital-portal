import React from 'react';
import { Download, ExternalLink, FileText } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import RowActions from '@/components/ui/RowActions';
import AdminListPage from '@/pages/admin/components/AdminListPage';
import { getAdminDocuments } from '@/api/adminPlatform';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { formatBytes } from '@/utils/formatNumber';

function workspaceOptions(pageData) {
    return [{ value: '', label: 'All Workspaces' }, ...(pageData.filter_workspaces || []).map((workspace) => ({ value: workspace.id, label: workspace.name }))];
}

function clientOptions(pageData) {
    return [{ value: '', label: 'All Clients' }, ...(pageData.filter_clients || []).map((client) => ({ value: client.id, label: `${client.name}${client.workspace ? ` / ${client.workspace}` : ''}` }))];
}


export default function AdminDocuments() {
    const { formatDateTime } = useDateFormatter();

    return (
        <AdminListPage
            title="Documents"
            description="Inspect documents across every workspace while preserving the disk and workspace recorded on each file."
            icon={FileText}
            fetcher={getAdminDocuments}
            searchPlaceholder="Search documents"
            emptyTitle="No documents found"
            emptyCopy="Documents linked or uploaded inside workspaces will appear here."
            filters={[
                { name: 'workspace_id', label: 'Workspace', options: workspaceOptions },
                { name: 'client_id', label: 'Client', options: clientOptions },
                { name: 'storage_disk', label: 'Storage', options: () => [{ value: '', label: 'All Storage' }, { value: 'local', label: 'Local' }, { value: 's3', label: 'S3' }, { value: 'none', label: 'Not tracked' }] },
            ]}
            columns={[
                { key: 'title', label: 'Document', render: (document) => document.title },
                { key: 'workspace', label: 'Workspace', render: (document) => document.workspace?.name || 'Platform' },
                { key: 'client', label: 'Client', render: (document) => document.client?.name || 'No client' },
                { key: 'created_by', label: 'Created By', render: (document) => document.created_by?.name || 'Unknown' },
                { key: 'storage', label: 'Storage', render: (document) => <Badge status={document.storage_disk || 'pending'}>{document.storage_disk || 'not tracked'}</Badge> },
                { key: 'size', label: 'Size', render: (document) => formatBytes(document.size_bytes) },
                { key: 'created_at', label: 'Created', render: (document) => formatDateTime(document.created_at, 'Recently') },
                {
                    key: 'actions',
                    label: 'Actions',
                    render: (document) => (
                        <RowActions
                            label={`Open actions for ${document.title}`}
                            actions={[
                                document.download_url ? { label: 'Download', icon: Download, href: document.download_url } : null,
                                document.external_url ? { label: 'Open link', icon: ExternalLink, href: document.external_url, external: true } : null,
                            ]}
                        />
                    ),
                },
            ]}
        />
    );
}
