import React, { useState } from 'react';
import { Plus } from 'lucide-react';
import Button from '@/components/ui/Button';
import ErrorState from '@/components/ui/ErrorState';
import LoadingState from '@/components/ui/LoadingState';
import Pagination from '@/components/ui/Pagination';
import Toast from '@/components/ui/Toast';
import FilterBar from '@/components/forms/FilterBar';
import SearchInput from '@/components/forms/SearchInput';
import Select from '@/components/forms/Select';
import { deleteTask, getTasks, updateTaskStatus } from '@/api/tasks';
import { usePaginatedList } from '@/hooks/usePaginatedList';
import { usePermissions } from '@/hooks/usePermissions';
import { useWorkspace } from '@/hooks/useWorkspace';
import TaskDetailsModal from '@/pages/tasks/components/TaskDetailsModal';
import TaskFormModal from '@/pages/tasks/components/TaskFormModal';
import TaskTable from '@/pages/tasks/components/TaskTable';

/**
 * Task list with its create/edit/view modals. Used by the Tasks page (with
 * filters), the client page (fixed to one client) and the dashboard.
 */
export default function TaskManager({ base, formOptions = {}, fixedFilters = {}, showFilters = false, showClient = true, perPage = 15, syncUrl = false, emptyCopy }) {
    const { can } = usePermissions();
    const { options, workspaceSlug } = useWorkspace();
    const [modal, setModal] = useState(null);
    const [feedback, setFeedback] = useState({ message: null, tone: 'success' });
    const list = usePaginatedList((params) => getTasks(workspaceSlug, { ...params, ...fixedFilters }), {
        initialFilters: showFilters ? { search: '', status: '', priority: '', assigned_to: '', client_id: '' } : {},
        perPage,
        syncUrl,
    });

    function report(message, tone = 'success') {
        setFeedback({ message, tone });
    }

    async function changeStatus(task, status) {
        try {
            const response = await updateTaskStatus(workspaceSlug, task.id, status);
            list.replaceRow(response.data);
            report(`"${task.title}" moved to ${response.data.status.replaceAll('_', ' ')}.`);
        } catch (error) {
            report(error.message, 'error');
        }
    }

    async function remove(task) {
        try {
            await deleteTask(workspaceSlug, task.id);
            report('Task deleted.');
            list.reload();
        } catch (error) {
            report(error.message, 'error');
        }
    }

    function saved(task, message) {
        setModal(null);
        report(message);
        list.reload();
    }

    return (
        <div className="form-stack">
            <Toast message={feedback.message} tone={feedback.tone} />
            {showFilters || can('tasks.create') ? (
                <FilterBar>
                    {showFilters ? (
                        <>
                            <SearchInput value={list.filters.search} onChange={(value) => list.setFilter('search', value)} placeholder="Search tasks" />
                            <Select label="Status" value={list.filters.status} onChange={(event) => list.setFilter('status', event.target.value)} options={[{ value: '', label: 'Any status' }, ...(options.task_statuses || [])]} />
                            <Select label="Priority" value={list.filters.priority} onChange={(event) => list.setFilter('priority', event.target.value)} options={[{ value: '', label: 'Any priority' }, ...(options.task_priorities || [])]} />
                            <Select label="Assignee" value={list.filters.assigned_to} onChange={(event) => list.setFilter('assigned_to', event.target.value)} options={[{ value: '', label: 'Anyone' }, { value: 'me', label: 'Assigned to me' }, ...(formOptions.assignees || [])]} />
                            <Select label="Client" value={list.filters.client_id} onChange={(event) => list.setFilter('client_id', event.target.value)} options={[{ value: '', label: 'All clients' }, ...(formOptions.clients || [])]} />
                        </>
                    ) : <span />}
                    {can('tasks.create') ? <Button icon={Plus} variant="accent" onClick={() => setModal({ type: 'form', task: null })}>Create task</Button> : null}
                </FilterBar>
            ) : null}
            {list.error ? <ErrorState title="Could not load tasks" copy={list.error} /> : null}
            {list.loading ? <LoadingState label="Loading tasks" /> : (
                <>
                    <TaskTable
                        tasks={list.rows}
                        base={base}
                        showClient={showClient}
                        emptyCopy={emptyCopy}
                        onView={(task) => setModal({ type: 'details', task })}
                        onEdit={(task) => setModal({ type: 'form', task })}
                        onStatusChange={changeStatus}
                        onDelete={remove}
                    />
                    <Pagination meta={list.meta} onPageChange={list.setPage} />
                </>
            )}
            {modal?.type === 'form' ? (
                <TaskFormModal task={modal.task} formOptions={formOptions} defaultClientId={fixedFilters.client_id} onClose={() => setModal(null)} onSaved={saved} />
            ) : null}
            {modal?.type === 'details' ? (
                <TaskDetailsModal task={modal.task} onClose={() => setModal(null)} onEdit={(task) => setModal({ type: 'form', task })} />
            ) : null}
        </div>
    );
}
