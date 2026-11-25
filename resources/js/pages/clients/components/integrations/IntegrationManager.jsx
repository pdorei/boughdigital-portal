import React, { useCallback, useEffect, useState } from 'react';
import { PlugZap } from 'lucide-react';
import EmptyState from '@/components/ui/EmptyState';
import ErrorState from '@/components/ui/ErrorState';
import LoadingState from '@/components/ui/LoadingState';
import Toast from '@/components/ui/Toast';
import { deleteIntegration, disconnectIntegration, getIntegrations } from '@/api/integrations';
import { useWorkspace } from '@/hooks/useWorkspace';
import AvailableIntegrations from '@/pages/clients/components/integrations/AvailableIntegrations';
import IntegrationFormModal from '@/pages/clients/components/integrations/IntegrationFormModal';
import IntegrationTable from '@/pages/clients/components/integrations/IntegrationTable';

export default function IntegrationManager({ client }) {
    const { options, workspaceSlug } = useWorkspace();
    const providers = options.integration_providers || [];
    const [integrations, setIntegrations] = useState([]);
    const [canCreate, setCanCreate] = useState(false);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState(null);
    const [modal, setModal] = useState(null);
    const [feedback, setFeedback] = useState({ message: null, tone: 'success' });

    const load = useCallback(async () => {
        setLoadError(null);

        try {
            const response = await getIntegrations(workspaceSlug, client.id);
            setIntegrations(response.data || []);
            setCanCreate(Boolean(response.meta?.can_create));
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
            setFeedback({ message: error.message, tone: 'error' });
        }
    }

    function saved(integration, message) {
        setModal(null);
        setFeedback({ message, tone: 'success' });
        load();
    }

    const available = providers.filter((provider) => !integrations.some((integration) => integration.provider === provider.value));
    const providerFor = (integration) => providers.find((provider) => provider.value === integration.provider);

    if (loadError) return <ErrorState title="Could not load integrations" copy={loadError} />;
    if (loading) return <LoadingState label="Loading integrations" />;

    return (
        <div className="form-stack">
            <Toast message={feedback.message} tone={feedback.tone} />
            {integrations.length ? (
                <IntegrationTable
                    integrations={integrations}
                    onEdit={(integration) => setModal({ provider: providerFor(integration), integration })}
                    onDisconnect={(integration) => run(() => disconnectIntegration(workspaceSlug, client.id, integration.id), `${integration.display_name} disconnected.`)}
                    onDelete={(integration) => run(() => deleteIntegration(workspaceSlug, client.id, integration.id), `${integration.display_name} deleted.`)}
                />
            ) : (
                <EmptyState
                    icon={PlugZap}
                    title="No integrations connected yet."
                    copy={canCreate ? 'Choose a service below to connect it to this client.' : 'A workspace admin can connect this client’s services.'}
                />
            )}
            {canCreate ? (
                <section className="form-stack">
                    <h3 className="card-title">Available integrations</h3>
                    <AvailableIntegrations providers={available} authTypes={options.integration_auth_types || []} onSelect={(provider) => setModal({ provider, integration: null })} />
                </section>
            ) : null}
            {modal ? (
                <IntegrationFormModal client={client} provider={modal.provider} integration={modal.integration} onClose={() => setModal(null)} onSaved={saved} />
            ) : null}
        </div>
    );
}
