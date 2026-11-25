import React, { useEffect, useState } from 'react';
import { CalendarRange, List, Plus } from 'lucide-react';
import FilterBar from '@/components/forms/FilterBar';
import Input from '@/components/forms/Input';
import SearchInput from '@/components/forms/SearchInput';
import Select from '@/components/forms/Select';
import Button from '@/components/ui/Button';
import ErrorState from '@/components/ui/ErrorState';
import LoadingState from '@/components/ui/LoadingState';
import Pagination from '@/components/ui/Pagination';
import Toast from '@/components/ui/Toast';
import { deleteCalendarEntry, getCalendarEntries, getPillars } from '@/api/content';
import { usePaginatedList } from '@/hooks/usePaginatedList';
import { useWorkspace } from '@/hooks/useWorkspace';
import CalendarAgenda from '@/pages/clients/components/content/calendar/CalendarAgenda';
import CalendarDetailsModal from '@/pages/clients/components/content/calendar/CalendarDetailsModal';
import CalendarFormModal from '@/pages/clients/components/content/calendar/CalendarFormModal';
import CalendarTable, { calendarActions } from '@/pages/clients/components/content/calendar/CalendarTable';

/**
 * "What content are we planning to produce?" — the client's editorial plan,
 * as a table or grouped by publish month.
 */
export default function CalendarManager({ client, base, writers, canCreateArticle }) {
    const { options, workspaceSlug } = useWorkspace();
    const [view, setView] = useState('agenda');
    const [pillars, setPillars] = useState([]);
    const [modal, setModal] = useState(null);
    const [feedback, setFeedback] = useState({ message: null, tone: 'success' });
    const list = usePaginatedList((params) => getCalendarEntries(workspaceSlug, client.id, params), {
        initialFilters: { search: '', status: '', writer: '', pillar: '', from: '', to: '' },
        perPage: 50,
    });
    const articleUrl = (articleId) => `${base}/clients/${client.slug}/articles/${articleId}`;

    useEffect(() => {
        getPillars(workspaceSlug, client.id).then((response) => setPillars(response.data || [])).catch(() => setPillars([]));
    }, [workspaceSlug, client.id]);

    async function remove(entry) {
        try {
            await deleteCalendarEntry(workspaceSlug, client.id, entry.id);
            setFeedback({ message: 'Calendar entry deleted.', tone: 'success' });
            list.reload();
        } catch (error) {
            setFeedback({ message: error.message, tone: 'error' });
        }
    }

    function saved(entry, message) {
        setModal(null);
        setFeedback({ message, tone: 'success' });
        list.reload();
    }

    const actions = (entry) => calendarActions(entry, {
        articleUrl,
        canCreateArticle,
        onView: (item) => setModal({ type: 'details', entry: item }),
        onEdit: (item) => setModal({ type: 'form', entry: item }),
        onDelete: remove,
        onCreateArticle: (item) => window.location.assign(`${base}/clients/${client.slug}/articles/create?calendar_entry=${item.id}`),
    });
    const View = view === 'agenda' ? CalendarAgenda : CalendarTable;

    return (
        <div className="form-stack">
            <Toast message={feedback.message} tone={feedback.tone} />
            <FilterBar>
                <SearchInput value={list.filters.search} onChange={(value) => list.setFilter('search', value)} placeholder="Search titles and keywords" />
                <Select label="Status" value={list.filters.status} onChange={(event) => list.setFilter('status', event.target.value)} options={[{ value: '', label: 'Any status' }, ...(options.content_calendar_statuses || [])]} />
                <Select label="Writer" value={list.filters.writer} onChange={(event) => list.setFilter('writer', event.target.value)} options={[{ value: '', label: 'Anyone' }, { value: 'me', label: 'Assigned to me' }, ...writers]} />
                <Select label="Pillar" value={list.filters.pillar} onChange={(event) => list.setFilter('pillar', event.target.value)} options={[{ value: '', label: 'All pillars' }, ...pillars.map((pillar) => ({ value: pillar.id, label: pillar.name }))]} />
                <Input icon={CalendarRange} type="date" label="From" value={list.filters.from} onChange={(event) => list.setFilter('from', event.target.value)} />
                <Input icon={CalendarRange} type="date" label="To" value={list.filters.to} onChange={(event) => list.setFilter('to', event.target.value)} />
            </FilterBar>
            <div className="between-row">
                <div className="inline-actions" role="group" aria-label="Calendar view">
                    <Button icon={CalendarRange} variant={view === 'agenda' ? 'primary' : 'secondary'} onClick={() => setView('agenda')} aria-pressed={view === 'agenda'}>By month</Button>
                    <Button icon={List} variant={view === 'list' ? 'primary' : 'secondary'} onClick={() => setView('list')} aria-pressed={view === 'list'}>List</Button>
                </div>
                {list.meta?.can_create ? <Button icon={Plus} variant="accent" onClick={() => setModal({ type: 'form', entry: null })}>Plan content</Button> : null}
            </div>
            {list.error ? <ErrorState title="Could not load the content calendar" copy={list.error} /> : null}
            {list.loading ? <LoadingState label="Loading content calendar" /> : (
                <>
                    <View entries={list.rows} actions={actions} onView={(entry) => setModal({ type: 'details', entry })} />
                    <Pagination meta={list.meta} onPageChange={list.setPage} />
                </>
            )}
            {modal?.type === 'form' ? (
                <CalendarFormModal client={client} entry={modal.entry} pillars={pillars} writers={writers} onClose={() => setModal(null)} onSaved={saved} />
            ) : null}
            {modal?.type === 'details' ? (
                <CalendarDetailsModal entry={modal.entry} articleUrl={articleUrl} onClose={() => setModal(null)} onEdit={(entry) => setModal({ type: 'form', entry })} />
            ) : null}
        </div>
    );
}
