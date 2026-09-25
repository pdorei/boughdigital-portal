import React from 'react';
import PageHeader from '@/components/layout/PageHeader';
import WorkspaceForm from '@/pages/admin/workspaces/components/WorkspaceForm';
import { useWorkspace } from '@/hooks/useWorkspace';

export default function WorkspaceEdit() {
    const { pageData } = useWorkspace();
    const workspace = pageData.workspace;

    return (
        <div className="page-stack">
            <PageHeader eyebrow="Super Admin" title={`Edit ${workspace?.name || 'Workspace'}`} description="Update branding, settings, slug, and activation state." />
            <WorkspaceForm workspace={workspace} />
        </div>
    );
}
