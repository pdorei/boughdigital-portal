import React from 'react';
import { CalendarDays, Clock3, Pencil, Type } from 'lucide-react';
import DisplayField from '@/components/forms/DisplayField';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import Modal from '@/components/ui/Modal';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { useWorkspace } from '@/hooks/useWorkspace';
import { optionLabel } from '@/utils/options';

// Mirrors the TaskFormModal layout so viewing and editing a task share the same geometry.
export default function TaskDetailsModal({ task, onClose, onEdit }) {
    const { formatDate, formatDateTime } = useDateFormatter();
    const { options } = useWorkspace();

    return (
        <Modal title="View task" onClose={onClose} wide>
            <div className="form-stack">
                <DisplayField icon={Type} label="Title">{task.title}</DisplayField>
                <div className="form-row">
                    <DisplayField label="Client">{task.client?.name || 'No client (workspace task)'}</DisplayField>
                    <DisplayField label="Assignee">{task.assignee?.name || 'Unassigned'}</DisplayField>
                </div>
                <div className="form-row">
                    <DisplayField label="Status">
                        <Badge status={task.status}>{optionLabel(options.task_statuses, task.status)}</Badge>
                    </DisplayField>
                    <DisplayField label="Priority">{optionLabel(options.task_priorities, task.priority)}</DisplayField>
                </div>
                <div className="form-row">
                    <DisplayField icon={CalendarDays} label="Due date">{formatDate(task.due_date, 'No due date')}</DisplayField>
                    <DisplayField icon={Clock3} label="Estimated hours">{task.estimated_hours ?? 'Not estimated'}</DisplayField>
                </div>
                <div className="form-row">
                    <DisplayField label="Created">{formatDateTime(task.created_at)}</DisplayField>
                </div>
                <DisplayField label="Description" multiline>{task.description || 'No description provided.'}</DisplayField>
                {task.can?.update ? (
                    <div className="inline-actions action-group-end">
                        <Button icon={Pencil} variant="secondary" onClick={() => onEdit(task)}>Edit task</Button>
                    </div>
                ) : null}
            </div>
        </Modal>
    );
}
