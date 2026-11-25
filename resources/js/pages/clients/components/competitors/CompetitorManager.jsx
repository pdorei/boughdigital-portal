import React, { useCallback, useEffect, useState } from 'react';
import { Plus } from 'lucide-react';
import Button from '@/components/ui/Button';
import ErrorState from '@/components/ui/ErrorState';
import LoadingState from '@/components/ui/LoadingState';
import Toast from '@/components/ui/Toast';
import { deleteCompetitor, getCompetitors } from '@/api/competitors';
import { useWorkspace } from '@/hooks/useWorkspace';
import CompetitorDetailsModal from '@/pages/clients/components/competitors/CompetitorDetailsModal';
import CompetitorFormModal from '@/pages/clients/components/competitors/CompetitorFormModal';
import CompetitorTable from '@/pages/clients/components/competitors/CompetitorTable';

/**
 * A client's competitors with their create/edit/view modals. The list is
 * capped server-side, so it is loaded whole rather than paginated.
 */
export default function CompetitorManager({ client }) {
    const { workspaceSlug } = useWorkspace();
    const [competitors, setCompetitors] = useState([]);
    const [meta, setMeta] = useState({ limit: 10, can_create: false });
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState(null);
    const [modal, setModal] = useState(null);
    const [feedback, setFeedback] = useState({ message: null, tone: 'success' });
    const atLimit = competitors.length >= meta.limit;

    const load = useCallback(async () => {
        setLoadError(null);

        try {
            const response = await getCompetitors(workspaceSlug, client.id);
            setCompetitors(response.data || []);
            setMeta(response.meta);
        } catch (error) {
            setLoadError(error.message);
        } finally {
            setLoading(false);
        }
    }, [workspaceSlug, client.id]);

    useEffect(() => {
        load();
    }, [load]);

    async function remove(competitor) {
        try {
            await deleteCompetitor(workspaceSlug, client.id, competitor.id);
            setFeedback({ message: 'Competitor deleted.', tone: 'success' });
            load();
        } catch (error) {
            setFeedback({ message: error.message, tone: 'error' });
        }
    }

    function saved(competitor, message, tone = 'success') {
        setModal(null);
        setFeedback({ message, tone });
        load();
    }

    return (
        <div className="form-stack">
            <Toast message={feedback.message} tone={feedback.tone} />
            <div className="between-row">
                <p className="section-copy">
                    {loading ? 'Loading competitors…' : `${competitors.length} of ${meta.limit} competitors`}
                    {!loading && atLimit && meta.can_create ? ' · Limit reached. Remove a competitor to add another.' : ''}
                </p>
                {meta.can_create ? (
                    <Button icon={Plus} variant="accent" onClick={() => setModal({ type: 'form', competitor: null })} disabled={atLimit} title={atLimit ? `A client can have up to ${meta.limit} competitors.` : undefined}>
                        Add competitor
                    </Button>
                ) : null}
            </div>
            {loadError ? <ErrorState title="Could not load competitors" copy={loadError} /> : null}
            {loading ? <LoadingState label="Loading competitors" /> : (
                <CompetitorTable
                    competitors={competitors}
                    onView={(competitor) => setModal({ type: 'details', competitor })}
                    onEdit={(competitor) => setModal({ type: 'form', competitor })}
                    onDelete={remove}
                />
            )}
            {modal?.type === 'form' ? (
                <CompetitorFormModal client={client} competitor={modal.competitor} maxKeywords={meta.max_keywords} onClose={() => setModal(null)} onSaved={saved} />
            ) : null}
            {modal?.type === 'details' ? (
                <CompetitorDetailsModal competitor={modal.competitor} onClose={() => setModal(null)} onEdit={(competitor) => setModal({ type: 'form', competitor })} />
            ) : null}
        </div>
    );
}
