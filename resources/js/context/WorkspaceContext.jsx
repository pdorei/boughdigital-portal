import React, { createContext, useMemo } from 'react';

export const WorkspaceContext = createContext(null);

export function WorkspaceProvider({ bootstrap, children }) {
    const value = useMemo(() => {
        const workspace = bootstrap.workspace || bootstrap.user?.workspace || null;

        return {
            datePreferenceOptions: bootstrap.datePreferenceOptions || {},
            datePreferences: bootstrap.datePreferences || {},
            navBadges: bootstrap.navBadges || {},
            options: bootstrap.options || {},
            page: bootstrap.page || 'welcome',
            pageData: bootstrap.pageData || {},
            section: bootstrap.section || null,
            userDatePreferences: bootstrap.userDatePreferences || {},
            workspace,
            workspaceBusinessDatePreferences: bootstrap.workspaceBusinessDatePreferences || {},
            workspaceDatePreferences: bootstrap.workspaceDatePreferences || {},
            workspaceName: workspace?.name || null,
            workspaceSlug: workspace?.slug || null,
        };
    }, [bootstrap]);

    return <WorkspaceContext.Provider value={value}>{children}</WorkspaceContext.Provider>;
}
