import React, { useState } from 'react';
import Card from '@/components/ui/Card';
import Tabs from '@/components/ui/Tabs';
import ActivityFeed from '@/pages/activity/components/ActivityFeed';
import ClientAiConfigPanel from '@/pages/clients/components/ai-config/ClientAiConfigPanel';
import ClientOverview from '@/pages/clients/components/ClientOverview';
import CompetitorManager from '@/pages/clients/components/competitors/CompetitorManager';
import ContentSection from '@/pages/clients/components/content/ContentSection';
import IntegrationManager from '@/pages/clients/components/integrations/IntegrationManager';
import DocumentManager from '@/pages/documents/components/DocumentManager';
import TaskManager from '@/pages/tasks/components/TaskManager';
import { tabFromUrl, writeTabToUrl } from '@/utils/tabs';

// SEO and Reports tabs join once those modules are built.
const TABS = ['Overview', 'Integrations', 'Documents', 'Tasks', 'Content', 'Competitors', 'AI Configuration', 'Activity'];

export default function ClientTabs({ client, team, base, formOptions, writers, can = {} }) {
    const [activeTab, setActiveTab] = useState(() => tabFromUrl('tab', TABS, 'Overview'));
    const clientOption = [{ value: client.id, label: client.name }];

    return (
        <Card>
            <Tabs
                tabs={TABS}
                activeTab={activeTab}
                onChange={(tab) => {
                    setActiveTab(tab);
                    writeTabToUrl('tab', tab);
                }}
            />
            {activeTab === 'Overview' ? <ClientOverview client={client} team={team} base={base} formOptions={formOptions} /> : null}
            {activeTab === 'Integrations' ? (
                <div className="card-pad">
                    <IntegrationManager client={client} />
                </div>
            ) : null}
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
            {activeTab === 'Content' ? (
                <div className="card-pad">
                    <ContentSection client={client} base={base} writers={writers} canCreateArticle={Boolean(can.create_article)} />
                </div>
            ) : null}
            {activeTab === 'Competitors' ? (
                <div className="card-pad">
                    <CompetitorManager client={client} />
                </div>
            ) : null}
            {activeTab === 'AI Configuration' ? (
                <div className="card-pad">
                    <ClientAiConfigPanel client={client} />
                </div>
            ) : null}
            {activeTab === 'Activity' ? <ActivityFeed base={base} clientId={client.id} /> : null}
        </Card>
    );
}
