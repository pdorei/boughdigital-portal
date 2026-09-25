import React from 'react';
import { Database } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import AdminListPage from '@/pages/admin/components/AdminListPage';
import { getAdminMcp } from '@/api/adminPlatform';
import { useDateFormatter } from '@/hooks/useDateFormatter';

function workspaceOptions(pageData) {
    return [{ value: '', label: 'All Workspaces' }, ...(pageData.filter_workspaces || []).map((workspace) => ({ value: workspace.id, label: workspace.name }))];
}

export default function AdminMcp() {
    const { formatDateTime } = useDateFormatter();

    return (
        <AdminListPage
            title="MCP"
            description="Monitor workspace MCP availability and tool exposure while keeping /mcp/{workspace} routing unchanged."
            icon={Database}
            fetcher={getAdminMcp}
            searchPlaceholder="Search workspaces"
            emptyTitle="No MCP workspaces found"
            emptyCopy="Active workspace MCP summaries will appear here."
            filters={[
                { name: 'workspace_id', label: 'Workspace', options: workspaceOptions },
            ]}
            columns={[
                { key: 'workspace', label: 'Workspace', render: (row) => row.workspace?.name || 'Workspace' },
                { key: 'status', label: 'MCP Status', render: (row) => <Badge status={row.status}>{row.status}</Badge> },
                { key: 'tools', label: 'Available Tools', render: (row) => row.tools.length },
                { key: 'tool_names', label: 'Enabled Tools', render: (row) => row.tools.join(', ') },
                { key: 'last_activity', label: 'Last Activity', render: (row) => formatDateTime(row.last_activity, 'No activity') },
                { key: 'errors_count', label: 'Errors', render: (row) => row.errors_count || 0 },
            ]}
        />
    );
}
