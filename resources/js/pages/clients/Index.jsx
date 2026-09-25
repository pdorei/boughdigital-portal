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
import { deleteClient, getClients, updateClient } from '@/api/clients';
import { usePaginatedList } from '@/hooks/usePaginatedList';
import { usePermissions } from '@/hooks/usePermissions';
import { useWorkspace } from '@/hooks/useWorkspace';
import ClientTable from '@/pages/clients/components/ClientTable';

export default function ClientsIndex({ base }) {
    const { can } = usePermissions();
    const { options, workspaceName, workspaceSlug } = useWorkspace();
    const [feedback, setFeedback] = useState({ message: null, tone: 'success' });
    const list = usePaginatedList((params) => getClients(workspaceSlug, params), {
        initialFilters: { search: '', status: 'active' },
        syncUrl: true,
    });

    async function run(action, successMessage) {
        try {
            await action();
            setFeedback({ message: successMessage, tone: 'success' });
            list.reload();
        } catch (error) {
            setFeedback({ message: error.message, tone: 'error' });
        }
    }

    return (
        <div className="page-stack">
            <PageHeader
                eyebrow={workspaceName}
                title="Clients"
                description="Client records, delivery status and the people who can work on them."
                action={can('clients.create') ? <Button icon={Plus} variant="accent" href={`${base}/clients/create`}>Add client</Button> : null}
            />
            <Toast message={feedback.message} tone={feedback.tone} />
            <FilterBar>
                <SearchInput value={list.filters.search} onChange={(value) => list.setFilter('search', value)} placeholder="Search by name or website" />
                <Select
                    label="Status"
                    value={list.filters.status}
                    onChange={(event) => list.setFilter('status', event.target.value)}
                    options={[{ value: '', label: 'All statuses' }, ...(options.client_statuses || [])]}
                />
            </FilterBar>
            {list.error ? <ErrorState title="Could not load clients" copy={list.error} /> : null}
            {list.loading ? <LoadingState label="Loading clients" /> : (
                <Card>
                    <ClientTable
                        clients={list.rows}
                        base={base}
                        onArchive={(client) => run(() => updateClient(workspaceSlug, client.id, { status: 'archived' }), `${client.name} archived.`)}
                        onRestore={(client) => run(() => updateClient(workspaceSlug, client.id, { status: 'active' }), `${client.name} restored.`)}
                        onDelete={(client) => run(() => deleteClient(workspaceSlug, client.id), `${client.name} deleted.`)}
                    />
                    <Pagination meta={list.meta} onPageChange={list.setPage} />
                </Card>
            )}
        </div>
    );
}
