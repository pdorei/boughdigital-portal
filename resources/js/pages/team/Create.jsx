import React from 'react';
import PageHeader from '@/components/layout/PageHeader';
import UserForm from '@/pages/team/components/UserForm';
import { useWorkspace } from '@/hooks/useWorkspace';

export default function TeamCreate({ base }) {
    const { workspaceName } = useWorkspace();

    return (
        <div className="page-stack">
            <PageHeader eyebrow={workspaceName || 'Workspace'} title="Add team member" description="Create an account for someone who works in this workspace." />
            <UserForm base={base} />
        </div>
    );
}
