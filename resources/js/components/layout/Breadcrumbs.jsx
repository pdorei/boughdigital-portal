import React from 'react';

export default function Breadcrumbs({ section, workspaceName, title }) {
    return <div className="crumbs">{section === 'admin' ? 'Admin' : workspaceName || 'Workspace'} / {title}</div>;
}
