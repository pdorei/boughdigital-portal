import React, { useState } from 'react';
import { Link2, Upload } from 'lucide-react';
import Button from '@/components/ui/Button';
import ErrorState from '@/components/ui/ErrorState';
import LoadingState from '@/components/ui/LoadingState';
import Pagination from '@/components/ui/Pagination';
import Toast from '@/components/ui/Toast';
import FilterBar from '@/components/forms/FilterBar';
import SearchInput from '@/components/forms/SearchInput';
import Select from '@/components/forms/Select';
import { deleteDocument, getDocuments } from '@/api/documents';
import { usePaginatedList } from '@/hooks/usePaginatedList';
import { usePermissions } from '@/hooks/usePermissions';
import { useWorkspace } from '@/hooks/useWorkspace';
import DocumentFormModal from '@/pages/documents/components/DocumentFormModal';
import DocumentTable from '@/pages/documents/components/DocumentTable';

export default function DocumentManager({ base, clients = [], clientId = null, perPage = 15, syncUrl = false }) {
    const { can } = usePermissions();
    const { options, workspaceSlug } = useWorkspace();
    const [modal, setModal] = useState(null);
    const [feedback, setFeedback] = useState({ message: null, tone: 'success' });
    const list = usePaginatedList((params) => getDocuments(workspaceSlug, clientId ? { ...params, client_id: clientId } : params), {
        initialFilters: { search: '', folder: '', source: '', ...(clientId ? {} : { client_id: '' }) },
        perPage,
        syncUrl,
    });
    const canAdd = can('documents.create') && clients.length > 0;

    async function remove(document) {
        try {
            await deleteDocument(workspaceSlug, document.id);
            setFeedback({ message: 'Document deleted.', tone: 'success' });
            list.reload();
        } catch (error) {
            setFeedback({ message: error.message, tone: 'error' });
        }
    }

    function saved(document, message) {
        setModal(null);
        setFeedback({ message, tone: 'success' });
        list.reload();
    }

    return (
        <div className="form-stack">
            <Toast message={feedback.message} tone={feedback.tone} />
            <FilterBar>
                <SearchInput value={list.filters.search} onChange={(value) => list.setFilter('search', value)} placeholder="Search documents" />
                <Select label="Folder" value={list.filters.folder} onChange={(event) => list.setFilter('folder', event.target.value)} options={[{ value: '', label: 'All folders' }, ...(options.document_folders || [])]} />
                <Select label="Source" value={list.filters.source} onChange={(event) => list.setFilter('source', event.target.value)} options={[{ value: '', label: 'Files and links' }, ...(options.document_sources || [])]} />
                {!clientId ? (
                    <Select label="Client" value={list.filters.client_id} onChange={(event) => list.setFilter('client_id', event.target.value)} options={[{ value: '', label: 'All clients' }, ...clients]} />
                ) : null}
                {canAdd ? (
                    <div className="inline-actions">
                        <Button icon={Upload} variant="accent" onClick={() => setModal({ mode: 'upload' })}>Upload document</Button>
                        <Button icon={Link2} variant="secondary" onClick={() => setModal({ mode: 'link' })}>Add link</Button>
                    </div>
                ) : null}
            </FilterBar>
            {list.error ? <ErrorState title="Could not load documents" copy={list.error} /> : null}
            {list.loading ? <LoadingState label="Loading documents" /> : (
                <>
                    <DocumentTable documents={list.rows} base={base} showClient={!clientId} onEdit={(document) => setModal({ mode: 'edit', document })} onDelete={remove} />
                    <Pagination meta={list.meta} onPageChange={list.setPage} />
                </>
            )}
            {modal ? (
                <DocumentFormModal mode={modal.mode} document={modal.document} clients={clients} defaultClientId={clientId} onClose={() => setModal(null)} onSaved={saved} />
            ) : null}
        </div>
    );
}
