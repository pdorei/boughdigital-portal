import React from 'react';
import { CheckCircle2, Eye, Pencil, Trash2 } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import EmptyState from '@/components/ui/EmptyState';
import RowActions from '@/components/ui/RowActions';
import Table from '@/components/ui/Table';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { useWorkspace } from '@/hooks/useWorkspace';
import { optionLabel } from '@/utils/options';

export default function TaskTable({ tasks, base, showClient = true, emptyCopy = 'No tasks match the current filters.', onView, onEdit, onStatusChange, onDelete }) {
    const { formatDate } = useDateFormatter();
    const { options } = useWorkspace();

    if (!tasks.length) {
        return <EmptyState icon={CheckCircle2} title="No tasks" copy={emptyCopy} />;
    }

    return (
        <Table
            columns={[
                { key: 'title', label: 'Task', render: (task) => <button type="button" className="client-link link-button" onClick={() => onView(task)}>{task.title}</button> },
                ...(showClient ? [{
                    key: 'client',
                    label: 'Client',
                    render: (task) => (task.client ? <a className="table-muted" href={`${base}/clients/${task.client.slug}`}>{task.client.name}</a> : <span className="table-muted">Workspace</span>),
                }] : []),
                { key: 'assignee', label: 'Assignee', render: (task) => task.assignee?.name || <span className="table-muted">Unassigned</span> },
                {
                    key: 'status',
                    label: 'Status',
                    render: (task) => (task.can?.change_status ? (
                        <select
                            className="inline-select"
                            aria-label={`Status for ${task.title}`}
                            value={task.status}
                            onChange={(event) => onStatusChange(task, event.target.value)}
                        >
                            {(options.task_statuses || []).map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                        </select>
                    ) : <Badge status={task.status}>{optionLabel(options.task_statuses, task.status)}</Badge>),
                },
                { key: 'priority', label: 'Priority', render: (task) => <Badge status={task.priority}>{optionLabel(options.task_priorities, task.priority)}</Badge> },
                { key: 'due_date', label: 'Due', render: (task) => formatDate(task.due_date, 'No due date') },
                {
                    key: 'actions',
                    label: 'Actions',
                    render: (task) => (
                        <RowActions
                            label={`Open actions for ${task.title}`}
                            actions={[
                                { label: 'View', icon: Eye, onClick: () => onView(task) },
                                task.can?.update ? { label: 'Edit', icon: Pencil, onClick: () => onEdit(task) } : null,
                                task.can?.delete ? {
                                    label: 'Delete',
                                    icon: Trash2,
                                    variant: 'danger',
                                    confirm: {
                                        title: `Delete "${task.title}"?`,
                                        description: 'The task is removed permanently. Tasks with logged time cannot be deleted; mark them completed instead.',
                                        confirmLabel: 'Delete task',
                                    },
                                    onClick: () => onDelete(task),
                                } : null,
                            ]}
                        />
                    ),
                },
            ]}
            rows={tasks}
            rowKey={(task) => task.id}
        />
    );
}
