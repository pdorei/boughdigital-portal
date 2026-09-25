import React from 'react';
import { ClipboardList } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import AdminListPage from '@/pages/admin/components/AdminListPage';
import { getAdminTasks } from '@/api/adminPlatform';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { useWorkspace } from '@/hooks/useWorkspace';
import { optionLabel } from '@/utils/options';

function workspaceOptions(pageData) {
    return [{ value: '', label: 'All Workspaces' }, ...(pageData.filter_workspaces || []).map((workspace) => ({ value: workspace.id, label: workspace.name }))];
}

function clientOptions(pageData) {
    return [{ value: '', label: 'All Clients' }, ...(pageData.filter_clients || []).map((client) => ({ value: client.id, label: `${client.name}${client.workspace ? ` / ${client.workspace}` : ''}` }))];
}

function userOptions(pageData) {
    return [{ value: '', label: 'All Assignees' }, ...(pageData.filter_users || []).map((user) => ({ value: user.id, label: user.name }))];
}

export default function AdminTasks() {
    const { formatDate, formatDateTime } = useDateFormatter();
    const { options } = useWorkspace();

    return (
        <AdminListPage
            title="Tasks"
            description="Review task workload across all workspaces without changing workspace ownership."
            icon={ClipboardList}
            fetcher={getAdminTasks}
            searchPlaceholder="Search tasks"
            emptyTitle="No tasks found"
            emptyCopy="Workspace tasks will appear here once they are created."
            filters={[
                { name: 'workspace_id', label: 'Workspace', options: workspaceOptions },
                { name: 'client_id', label: 'Client', options: clientOptions },
                { name: 'assigned_to', label: 'Assignee', options: userOptions },
                { name: 'status', label: 'Status', options: () => [{ value: '', label: 'All statuses' }, ...(options.task_statuses || [])] },
            ]}
            columns={[
                { key: 'title', label: 'Task', render: (task) => task.title },
                { key: 'workspace', label: 'Workspace', render: (task) => task.workspace?.name || 'Platform' },
                { key: 'client', label: 'Client', render: (task) => task.client?.name || 'No client' },
                { key: 'assigned_to', label: 'Assigned To', render: (task) => task.assigned_to?.name || 'Unassigned' },
                { key: 'created_by', label: 'Created By', render: (task) => task.created_by?.name || 'Unknown' },
                { key: 'status', label: 'Status', render: (task) => <Badge status={task.status}>{optionLabel(options.task_statuses, task.status)}</Badge> },
                { key: 'due_date', label: 'Due Date', render: (task) => formatDate(task.due_date, 'No due date') },
                { key: 'updated_at', label: 'Updated', render: (task) => formatDateTime(task.updated_at, 'Recently') },
            ]}
        />
    );
}
