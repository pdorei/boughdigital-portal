import React, { useState } from 'react';
import Card from '@/components/ui/Card';
import Tabs from '@/components/ui/Tabs';
import ActivityFeed from '@/pages/activity/components/ActivityFeed';
import ClientOverview from '@/pages/clients/components/ClientOverview';
import DocumentManager from '@/pages/documents/components/DocumentManager';
import TaskManager from '@/pages/tasks/components/TaskManager';

// Content, SEO and Reports tabs join once those modules are built.
const TABS = ['Overview', 'Tasks', 'Documents', 'Activity'];

export default function ClientTabs({ client, team, base, formOptions }) {
    const [activeTab, setActiveTab] = useState('Overview');
    const clientOption = [{ value: client.id, label: client.name }];

    return (
        <Card>
            <Tabs tabs={TABS} activeTab={activeTab} onChange={setActiveTab} />
            {activeTab === 'Overview' ? <ClientOverview client={client} team={team} base={base} formOptions={formOptions} /> : null}
            {activeTab === 'Tasks' ? (
                <div className="card-pad">
                    <TaskManager base={base} formOptions={{ ...formOptions, clients: clientOption }} fixedFilters={{ client_id: client.id }} showClient={false} />
                </div>
            ) : null}
            {activeTab === 'Documents' ? (
                <div className="card-pad">
                    <DocumentManager base={base} clients={clientOption} clientId={client.id} />
                </div>
            ) : null}
            {activeTab === 'Activity' ? <ActivityFeed base={base} clientId={client.id} /> : null}
        </Card>
    );
}
