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
import { activateAdminUser, deactivateAdminUser, deleteAdminUser, getAdminUsers } from '@/api/users';
import { usePaginatedList } from '@/hooks/usePaginatedList';
import { useWorkspace } from '@/hooks/useWorkspace';
import UserTable from '@/pages/team/components/UserTable';

const STATUS_OPTIONS = [
    { value: '', label: 'All statuses' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

export default function AdminUsersIndex() {
    const { options, pageData } = useWorkspace();
    const [feedback, setFeedback] = useState({ message: null, tone: 'success' });
    const list = usePaginatedList(getAdminUsers, {
        initialFilters: { search: '', workspace_id: '', role: '', status: '' },
        syncUrl: true,
    });
    const workspaceOptions = [
        { value: '', label: 'All workspaces' },
        ...(pageData.filter_workspaces || []).map((workspace) => ({ value: workspace.id, label: workspace.name })),
    ];

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
            <PageHeader eyebrow="Super Admin" title="Users" description="Platform and workspace accounts across every workspace." action={<Button icon={Plus} variant="accent" href="/admin/users/create">Add user</Button>} />
            <Toast message={feedback.message} tone={feedback.tone} />
            <FilterBar>
                <SearchInput value={list.filters.search} onChange={(value) => list.setFilter('search', value)} placeholder="Search by name or email" />
                <Select label="Workspace" value={list.filters.workspace_id} onChange={(event) => list.setFilter('workspace_id', event.target.value)} options={workspaceOptions} />
                <Select label="Role" value={list.filters.role} onChange={(event) => list.setFilter('role', event.target.value)} options={[{ value: '', label: 'All roles' }, ...(options.roles || [])]} />
                <Select label="Status" value={list.filters.status} onChange={(event) => list.setFilter('status', event.target.value)} options={STATUS_OPTIONS} />
            </FilterBar>
            {list.error ? <ErrorState title="Could not load users" copy={list.error} /> : null}
            {list.loading ? <LoadingState label="Loading users" /> : (
                <Card>
                    <UserTable
                        users={list.rows}
                        base="/admin/users"
                        showWorkspace
                        onToggle={(user) => run(
                            () => (user.is_active ? deactivateAdminUser(user.id) : activateAdminUser(user.id)),
                            `${user.name} ${user.is_active ? 'deactivated' : 'activated'}.`,
                        )}
                        onDelete={(user) => run(() => deleteAdminUser(user.id), `${user.name} deleted.`, true)}
                    />
                    <Pagination meta={list.meta} onPageChange={list.setPage} />
                </Card>
            )}
        </div>
    );
}
