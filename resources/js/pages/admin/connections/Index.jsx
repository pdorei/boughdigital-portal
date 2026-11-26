import React from 'react';
import Toast from '@/components/ui/Toast';
import PageHeader from '@/components/layout/PageHeader';
import ConnectionsPanel from '@/pages/admin/connections/components/ConnectionsPanel';
import { useAuth } from '@/hooks/useAuth';

export default function AdminConnections() {
    const { status } = useAuth();

    return (
        <div className="page-stack">
            <PageHeader
                eyebrow="Services"
                title="Connections"
                description="Platform-level service accounts: Upwork (OAuth) and Claude (API key). Keys and tokens are stored encrypted and never shown again."
            />
            {/* Messages from a redirect, such as the Upwork OAuth callback. */}
            <Toast message={status} tone={status && /fail|cancel|not set/i.test(status) ? 'error' : 'success'} />
            <ConnectionsPanel />
        </div>
    );
}
