import React from 'react';
import { ExternalLink, Pencil } from 'lucide-react';
import Button from '@/components/ui/Button';
import PageHeader from '@/components/layout/PageHeader';
import { useWorkspace } from '@/hooks/useWorkspace';
import ClientTabs from '@/pages/clients/components/ClientTabs';

export default function ClientShow({ base }) {
    const { pageData } = useWorkspace();
    const client = pageData.client;

    return (
        <div className="page-stack">
            <PageHeader
                eyebrow="Client"
                title={client.name}
                description={client.status === 'archived' ? 'This client is archived. Its history is kept for reference.' : client.website_url}
                action={(
                    <div className="inline-actions">
                        {client.can?.update ? <Button icon={Pencil} variant="secondary" href={`${base}/clients/${client.slug}/edit`}>Edit</Button> : null}
                        {client.website_url ? <Button icon={ExternalLink} variant="secondary" href={client.website_url} target="_blank" rel="noopener noreferrer">Open site</Button> : null}
                    </div>
                )}
            />
            <ClientTabs client={client} team={pageData.team || []} base={base} formOptions={pageData.form_options || {}} />
        </div>
    );
}
