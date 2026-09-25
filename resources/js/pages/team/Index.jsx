import React, { useState } from 'react';
import { Plus } from 'lucide-react';
import Button from '@/components/ui/Button';
import Card from '@/components/ui/Card';
import ErrorState from '@/components/ui/ErrorState';
import LoadingState from '@/components/ui/LoadingState';
import Pagination from '@/components/ui/Pagination';
import Toast from '@/components/ui/Toast';
import FilterBar from '@/components/forms/FilterBar';
import SearchInput from '@/components/forms/SearchInput';
import Select from '@/components/forms/Select';
import PageHeader from '@/components/layout/PageHeader';
import { activateWorkspaceUser, deactivateWorkspaceUser, getWorkspaceUsers, removeWorkspaceMember } from '@/api/users';
import { usePaginatedList } from '@/hooks/usePaginatedList';
import { useWorkspace } from '@/hooks/useWorkspace';
import UserTable from '@/pages/team/components/UserTable';

const STATUS_OPTIONS = [
    { value: '', label: 'All statuses' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

export default function TeamIndex({ base }) {
    const { options, workspaceName, workspaceSlug } = useWorkspace();
    const [feedback, setFeedback] = useState({ message: null, tone: 'success' });
    const list = usePaginatedList((params) => getWorkspaceUsers(workspaceSlug, params), {
        initialFilters: { search: '', role: '', status: '' },
        syncUrl: true,
    });

    async function run(action, message, reload = false) {
        try {
            const response = await action();
            reload ? list.reload() : list.replaceRow(response.data);
            setFeedback({ message, tone: 'success' });
        } catch (error) {
            setFeedback({ message: error.message, tone: 'error' });
        }
    }

    return (
        <div className="page-stack">
            <PageHeader
                eyebrow={workspaceName}
                title="Team"
                description="Members of this workspace, their roles and the clients they can access."
                action={<Button icon={Plus} variant="accent" href={`${base}/create`}>Add member</Button>}
            />
            <Toast message={feedback.message} tone={feedback.tone} />
            <FilterBar>
                <SearchInput value={list.filters.search} onChange={(value) => list.setFilter('search', value)} placeholder="Search by name or email" />
                <Select label="Role" value={list.filters.role} onChange={(event) => list.setFilter('role', event.target.value)} options={[{ value: '', label: 'All roles' }, ...(options.workspace_roles || [])]} />
                <Select label="Status" value={list.filters.status} onChange={(event) => list.setFilter('status', event.target.value)} options={STATUS_OPTIONS} />
            </FilterBar>
            {list.error ? <ErrorState title="Could not load team" copy={list.error} /> : null}
            {list.loading ? <LoadingState label="Loading team" /> : (
                <Card>
                    <UserTable
                        users={list.rows}
                        base={base}
                        onToggle={(user) => run(
                            () => (user.is_active ? deactivateWorkspaceUser(workspaceSlug, user.id) : activateWorkspaceUser(workspaceSlug, user.id)),
                            `${user.name} ${user.is_active ? 'deactivated' : 'activated'}.`,
                        )}
                        onRemoveFromWorkspace={(user) => run(() => removeWorkspaceMember(workspaceSlug, user.id), `${user.name} removed from the workspace.`, true)}
                    />
                    <Pagination meta={list.meta} onPageChange={list.setPage} />
                </Card>
            )}
        </div>
    );
}
