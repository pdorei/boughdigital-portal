import React from 'react';
import { Pencil } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import Modal from '@/components/ui/Modal';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { useWorkspace } from '@/hooks/useWorkspace';
import { optionLabel } from '@/utils/options';

export default function TaskDetailsModal({ task, onClose, onEdit }) {
    const { formatDate, formatDateTime } = useDateFormatter();
    const { options } = useWorkspace();

    const details = [
        ['Status', <Badge key="status" status={task.status}>{optionLabel(options.task_statuses, task.status)}</Badge>],
        ['Priority', optionLabel(options.task_priorities, task.priority)],
        ['Client', task.client?.name || 'Workspace task'],
        ['Assignee', task.assignee?.name || 'Unassigned'],
        ['Due date', formatDate(task.due_date, 'No due date')],
        ['Estimated hours', task.estimated_hours ?? 'Not estimated'],
        ['Created', formatDateTime(task.created_at)],
    ];

    return (
        <Modal
            title={task.title}
            onClose={onClose}
            wide
            footer={task.can?.update ? <Button icon={Pencil} variant="secondary" onClick={() => onEdit(task)}>Edit task</Button> : null}
        >
            <p className="section-copy task-description">{task.description || 'No description provided.'}</p>
            <div className="list">
                {details.map(([label, value]) => (
                    <div className="list-row" key={label}><strong>{label}</strong><span className="muted">{value}</span></div>
                ))}
            </div>
        </Modal>
    );
}
