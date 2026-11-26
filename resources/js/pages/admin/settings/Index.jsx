import React, { useState } from 'react';
import Card from '@/components/ui/Card';
import Tabs from '@/components/ui/Tabs';
import Toast from '@/components/ui/Toast';
import PageHeader from '@/components/layout/PageHeader';
import ConnectionsSettings from '@/pages/admin/settings/Connections';
import StorageSettingsSection from '@/pages/admin/settings/Storage';
import UpworkSettings from '@/pages/admin/settings/Upwork';
import { useAuth } from '@/hooks/useAuth';
import { tabFromUrl, writeTabToUrl } from '@/utils/tabs';

const TABS = ['Storage', 'Connections', 'Upwork'];

export default function AdminSettings() {
    const { status } = useAuth();
    const [tab, setTab] = useState(() => tabFromUrl('tab', TABS, 'Storage'));

    function changeTab(next) {
        setTab(next);
        writeTabToUrl('tab', next);
    }

    return (
        <div className="page-stack">
            <PageHeader
                eyebrow="Super Admin"
                title="Settings"
                description="Platform-wide configuration: file storage, service connections and the Upwork automation."
            />
            {/* Messages from a redirect, such as the Upwork OAuth callback. */}
            <Toast message={status} tone={status && /fail|cancel|not set/i.test(status) ? 'error' : 'success'} />
            <Tabs tabs={TABS} activeTab={tab} onChange={changeTab} label="Settings sections" />
            {tab === 'Storage' ? (
                <Card title="File Storage">
                    <div className="card-pad">
                        <StorageSettingsSection />
                    </div>
                </Card>
            ) : null}
            {tab === 'Connections' ? <ConnectionsSettings /> : null}
            {tab === 'Upwork' ? <UpworkSettings /> : null}
        </div>
    );
}
