import React from 'react';
import { WORKSPACE_NAV_ITEMS } from '@/constants/routes';
import { useWorkspace } from '@/hooks/useWorkspace';
import AppLayout from '@/layouts/AppLayout';

export default function WorkspaceLayout({ title, children }) {
    const { workspace, workspaceSlug } = useWorkspace();

    return (
        <AppLayout nav={WORKSPACE_NAV_ITEMS} base={`/${workspaceSlug}`} workspace={workspace} section="workspace" title={title}>
            {children}
        </AppLayout>
    );
}
