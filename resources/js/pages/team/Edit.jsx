import React from 'react';
import PageHeader from '@/components/layout/PageHeader';
import UserForm from '@/pages/team/components/UserForm';
import { useWorkspace } from '@/hooks/useWorkspace';

export default function TeamEdit({ base }) {
    const { pageData, workspaceName } = useWorkspace();
    const user = pageData.selected_user;

    return (
        <div className="page-stack">
            <PageHeader eyebrow={workspaceName || 'Workspace'} title={`Edit ${user.name}`} description="Update role, status and client access." />
            <UserForm user={user} base={base} />
        </div>
    );
}
