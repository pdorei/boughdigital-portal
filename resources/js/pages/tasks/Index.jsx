import React from 'react';
import Card from '@/components/ui/Card';
import PageHeader from '@/components/layout/PageHeader';
import { useWorkspace } from '@/hooks/useWorkspace';
import TaskManager from '@/pages/tasks/components/TaskManager';

export default function TasksIndex({ base }) {
    const { pageData, workspaceName } = useWorkspace();

    return (
        <div className="page-stack">
            <PageHeader eyebrow={workspaceName} title="Tasks" description="Delivery work across the clients you can access." />
            <Card padded>
                <TaskManager base={base} formOptions={pageData.form_options} showFilters syncUrl />
            </Card>
        </div>
    );
}
