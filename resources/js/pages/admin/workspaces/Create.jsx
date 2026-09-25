import React from 'react';
import PageHeader from '@/components/layout/PageHeader';
import WorkspaceForm from '@/pages/admin/workspaces/components/WorkspaceForm';

export default function WorkspaceCreate() {
    return (
        <div className="page-stack">
            <PageHeader eyebrow="Super Admin" title="Create Workspace" description="Add a tenant workspace with branding, settings, and activation state." />
            <WorkspaceForm />
        </div>
    );
}
