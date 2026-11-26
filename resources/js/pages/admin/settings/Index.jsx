import React, { useEffect } from 'react';
import Card from '@/components/ui/Card';
import PageHeader from '@/components/layout/PageHeader';
import StorageSettingsSection from '@/pages/admin/settings/Storage';

// Connections and Upwork used to be tabs here; keep old bookmarks working.
const MOVED = { connections: '/admin/connections', upwork: '/admin/upwork/configuration' };

export default function AdminSettings() {
    const moved = MOVED[new URLSearchParams(window.location.search).get('tab')];

    useEffect(() => {
        if (moved) window.location.replace(moved);
    }, [moved]);

    return (
        <div className="page-stack">
            <PageHeader eyebrow="Super Admin" title="Settings" description="Platform-wide configuration." />
            <Card title="File Storage">
                <div className="card-pad">
                    <StorageSettingsSection />
                </div>
            </Card>
        </div>
    );
}
