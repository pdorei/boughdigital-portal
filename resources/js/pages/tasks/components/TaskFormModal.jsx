import React, { useState } from 'react';
import { CalendarDays, Clock3, Save, Type } from 'lucide-react';
import Input from '@/components/forms/Input';
import Select from '@/components/forms/Select';
import Textarea from '@/components/forms/Textarea';
import Button from '@/components/ui/Button';
import ErrorState from '@/components/ui/ErrorState';
import Modal from '@/components/ui/Modal';
import { createTask, updateTask } from '@/api/tasks';
import { useWorkspace } from '@/hooks/useWorkspace';

export default function TaskFormModal({ task = null, formOptions = {}, defaultClientId = null, onClose, onSaved }) {
    const { options, workspaceSlug } = useWorkspace();
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState({});
    const [formError, setFormError] = useState(null);
    const isEditing = Boolean(task?.id);

    async function handleSubmit(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        setFormError(null);

        const form = new FormData(event.currentTarget);
        const payload = {
            title: form.get('title'),
            description: form.get('description') || null,
            client_id: form.get('client_id') || null,
            assigned_to: form.get('assigned_to') || null,
            status: form.get('status'),
            priority: form.get('priority'),
            due_date: form.get('due_date') || null,
            estimated_hours: form.get('estimated_hours') || null,
        };

        try {
            const response = isEditing ? await updateTask(workspaceSlug, task.id, payload) : await createTask(workspaceSlug, payload);
            onSaved(response.data, isEditing ? 'Task updated successfully.' : 'Task created successfully.');
        } catch (error) {
            setErrors(error.errors);
            setFormError(error.message);
            setSubmitting(false);
        }
    }

    return (
        <Modal title={isEditing ? 'Edit task' : 'Create task'} onClose={onClose} busy={submitting} wide>
            <form className="form-stack" onSubmit={handleSubmit}>
                {formError ? <ErrorState title="Could not save task" copy={formError} /> : null}
                <Input icon={Type} name="title" label="Title" defaultValue={task?.title || ''} error={errors.title?.[0]} required />
                <Textarea name="description" label="Description" defaultValue={task?.description || ''} error={errors.description?.[0]} />
                <div className="form-row">
                    <Select
                        name="client_id"
                        label="Client"
                        defaultValue={task?.client_id ?? defaultClientId ?? ''}
                        options={[{ value: '', label: 'No client (workspace task)' }, ...(formOptions.clients || [])]}
                        error={errors.client_id?.[0]}
                    />
                    <Select
                        name="assigned_to"
                        label="Assignee"
                        defaultValue={task?.assigned_to ?? ''}
                        options={[{ value: '', label: 'Unassigned' }, ...(formOptions.assignees || [])]}
                        error={errors.assigned_to?.[0]}
                    />
                </div>
                <div className="form-row">
                    <Select name="status" label="Status" defaultValue={task?.status || 'backlog'} options={options.task_statuses || []} error={errors.status?.[0]} />
                    <Select name="priority" label="Priority" defaultValue={task?.priority || 'normal'} options={options.task_priorities || []} error={errors.priority?.[0]} />
                </div>
                <div className="form-row">
                    <Input icon={CalendarDays} name="due_date" type="date" label="Due date" defaultValue={task?.due_date || ''} error={errors.due_date?.[0]} />
                    <Input icon={Clock3} name="estimated_hours" type="number" min="0" step="0.25" label="Estimated hours" defaultValue={task?.estimated_hours ?? ''} error={errors.estimated_hours?.[0]} />
                </div>
                <div className="inline-actions action-group-end">
                    <Button variant="secondary" onClick={onClose} disabled={submitting}>Cancel</Button>
                    <Button icon={Save} type="submit" disabled={submitting}>{submitting ? 'Saving...' : 'Save task'}</Button>
                </div>
            </form>
        </Modal>
    );
}
