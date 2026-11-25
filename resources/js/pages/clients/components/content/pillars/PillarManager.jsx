import React, { useCallback, useEffect, useState } from 'react';
import { Plus } from 'lucide-react';
import Button from '@/components/ui/Button';
import ErrorState from '@/components/ui/ErrorState';
import LoadingState from '@/components/ui/LoadingState';
import Toast from '@/components/ui/Toast';
import { deletePillar, getPillars, updatePillar } from '@/api/content';
import { useWorkspace } from '@/hooks/useWorkspace';
import PillarFormModal from '@/pages/clients/components/content/pillars/PillarFormModal';
import PillarTable from '@/pages/clients/components/content/pillars/PillarTable';

export default function PillarManager({ client }) {
    const { workspaceSlug } = useWorkspace();
    const [pillars, setPillars] = useState([]);
    const [canCreate, setCanCreate] = useState(false);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState(null);
    const [modal, setModal] = useState(null);
    const [feedback, setFeedback] = useState({ message: null, tone: 'success' });

    const load = useCallback(async () => {
        try {
            const response = await getPillars(workspaceSlug, client.id);
            setPillars(response.data || []);
            setCanCreate(Boolean(response.meta?.can_create));
            setLoadError(null);
        } catch (error) {
            setLoadError(error.message);
        } finally {
            setLoading(false);
        }
    }, [workspaceSlug, client.id]);

    useEffect(() => {
        load();
    }, [load]);

    async function run(action, message) {
        try {
            await action();
            setFeedback({ message, tone: 'success' });
            load();
        } catch (error) {
            setFeedback({ message: error.errors?.pillar?.[0] || error.message, tone: 'error' });
        }
    }

    function saved(pillar, message) {
        setModal(null);
        setFeedback({ message, tone: 'success' });
        load();
    }

    if (loadError) return <ErrorState title="Could not load pillars" copy={loadError} />;
    if (loading) return <LoadingState label="Loading pillars" />;

    return (
        <div className="form-stack">
            <Toast message={feedback.message} tone={feedback.tone} />
            <div className="between-row">
                <p className="section-copy">The main topics and themes this client publishes about.</p>
                {canCreate ? <Button icon={Plus} variant="accent" onClick={() => setModal({ pillar: null })}>Add pillar</Button> : null}
            </div>
            <PillarTable
                pillars={pillars}
                onEdit={(pillar) => setModal({ pillar })}
                onToggle={(pillar) => run(() => updatePillar(workspaceSlug, client.id, pillar.id, { active: !pillar.active }), `${pillar.name} ${pillar.active ? 'deactivated' : 'activated'}.`)}
                onDelete={(pillar) => run(() => deletePillar(workspaceSlug, client.id, pillar.id), `${pillar.name} deleted.`)}
            />
            {modal ? <PillarFormModal client={client} pillar={modal.pillar} onClose={() => setModal(null)} onSaved={saved} /> : null}
        </div>
    );
}
