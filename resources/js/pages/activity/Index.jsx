import React from 'react';
import Card from '@/components/ui/Card';
import PageHeader from '@/components/layout/PageHeader';
import { useWorkspace } from '@/hooks/useWorkspace';
import ActivityFeed from '@/pages/activity/components/ActivityFeed';

export default function ActivityIndex({ base }) {
    const { workspaceName } = useWorkspace();

    return (
        <div className="page-stack">
            <PageHeader eyebrow={workspaceName} title="Activity" description="An audit trail of changes to clients, tasks, documents, the team and workspace settings." />
            <Card>
                <ActivityFeed base={base} syncUrl />
            </Card>
        </div>
    );
}
