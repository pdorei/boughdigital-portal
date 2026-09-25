import React from 'react';
import Card from '@/components/ui/Card';
import StatsGrid from '@/components/ui/StatsGrid';
import PageHeader from '@/components/layout/PageHeader';
import { usePermissions } from '@/hooks/usePermissions';
import { useWorkspace } from '@/hooks/useWorkspace';
import ActivityFeed from '@/pages/activity/components/ActivityFeed';
import TaskManager from '@/pages/tasks/components/TaskManager';

export default function Dashboard({ base }) {
    const { can } = usePermissions();
    const { pageData, workspaceName } = useWorkspace();
    const stats = pageData.stats;

    return (
        <div className="page-stack">
            <PageHeader eyebrow={workspaceName} title="Dashboard" description="Your open work and the clients you can access." />
            <StatsGrid stats={[
                { label: 'Clients', value: stats.clients, trend: 'Visible to you' },
                { label: 'Open tasks', value: stats.open_tasks, trend: 'Across your clients' },
                { label: 'Assigned to you', value: stats.my_open_tasks, trend: 'Open tasks' },
                { label: 'Documents', value: stats.documents, trend: 'Files and links' },
            ]} />
            <div className="grid-two">
                <Card title="My open tasks" padded>
                    <TaskManager base={base} fixedFilters={{ assigned_to: 'me', open: 1 }} perPage={5} emptyCopy="Nothing is assigned to you right now." />
                </Card>
                {can('activity.view') ? (
                    <Card title="Recent activity">
                        <ActivityFeed base={base} perPage={6} paginate={false} />
                    </Card>
                ) : null}
            </div>
        </div>
    );
}
