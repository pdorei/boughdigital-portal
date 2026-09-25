import React from 'react';
import Card from '@/components/ui/Card';
import PageHeader from '@/components/layout/PageHeader';
import ClientForm from '@/pages/clients/components/ClientForm';

export default function ClientCreate({ base }) {
    return (
        <div className="page-stack">
            <PageHeader eyebrow="Clients" title="New Client" description="Create a workspace-scoped client record." />
            <Card title="Client Details" padded>
                <ClientForm base={base} />
            </Card>
        </div>
    );
}
