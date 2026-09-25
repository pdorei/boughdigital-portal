import React, { useState } from 'react';
import { Plus } from 'lucide-react';
import Button from '@/components/ui/Button';
import Card from '@/components/ui/Card';
import ErrorState from '@/components/ui/ErrorState';
import LoadingState from '@/components/ui/LoadingState';
import Pagination from '@/components/ui/Pagination';
import StatsGrid from '@/components/ui/StatsGrid';
import Toast from '@/components/ui/Toast';
import FilterBar from '@/components/forms/FilterBar';
import SearchInput from '@/components/forms/SearchInput';
import Select from '@/components/forms/Select';
import PageHeader from '@/components/layout/PageHeader';
import { activateWorkspace, deactivateWorkspace, getWorkspaces } from '@/api/workspaces';
import { usePaginatedList } from '@/hooks/usePaginatedList';
import { useWorkspace } from '@/hooks/useWorkspace';
import WorkspaceTable from '@/pages/admin/workspaces/components/WorkspaceTable';

const STATUS_OPTIONS = [
    { value: '', label: 'All statuses' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

export default function WorkspaceIndex() {
    const { pageData } = useWorkspace();
    const [feedback, setFeedback] = useState({ message: null, tone: 'success' });
    const list = usePaginatedList(getWorkspaces, { initialFilters: { search: '', status: '' }, syncUrl: true });

    async function toggle(workspace) {
        try {
            const response = workspace.is_active ? await deactivateWorkspace(workspace.id) : await activateWorkspace(workspace.id);
            list.replaceRow(response.data);
            setFeedback({ message: `Workspace ${workspace.is_active ? 'deactivated' : 'activated'}.`, tone: 'success' });
        } catch (error) {
            setFeedback({ message: error.message, tone: 'error' });
        }
    }

    return (
        <div className="page-stack">
            <PageHeader
                eyebrow="Super Admin"
                title="Workspaces"
                description="Create, review and activate workspace environments across the platform."
                action={<Button icon={Plus} variant="accent" href="/admin/workspaces/create">Add workspace</Button>}
            />
            <StatsGrid stats={pageData.stats || []} />
            <Toast message={feedback.message} tone={feedback.tone} />
            <FilterBar>
                <SearchInput value={list.filters.search} onChange={(value) => list.setFilter('search', value)} placeholder="Search workspaces" />
                <Select label="Status" value={list.filters.status} onChange={(event) => list.setFilter('status', event.target.value)} options={STATUS_OPTIONS} />
            </FilterBar>
            {list.error ? <ErrorState title="Could not load workspaces" copy={list.error} /> : null}
            {list.loading ? <LoadingState label="Loading workspaces" /> : (
                <Card>
                    <WorkspaceTable workspaces={list.rows} onToggle={toggle} />
                    <Pagination meta={list.meta} onPageChange={list.setPage} />
                </Card>
            )}
        </div>
    );
}
