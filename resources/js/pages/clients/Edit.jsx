import React from 'react';
import Card from '@/components/ui/Card';
import PageHeader from '@/components/layout/PageHeader';
import ClientForm from '@/pages/clients/components/ClientForm';
import { useWorkspace } from '@/hooks/useWorkspace';

export default function ClientEdit({ base }) {
    const { pageData } = useWorkspace();

    return (
        <div className="page-stack">
            <PageHeader eyebrow="Clients" title={`Edit ${pageData.client.name}`} description="Update the client's details and status." />
            <Card title="Client details" padded>
                <ClientForm client={pageData.client} base={base} />
            </Card>
        </div>
    );
}
