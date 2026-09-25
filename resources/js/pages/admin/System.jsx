import React from 'react';
import Card from '@/components/ui/Card';
import MetricCard from '@/components/ui/MetricCard';
import PageHeader from '@/components/layout/PageHeader';
import StorageSettingsSection from '@/pages/admin/settings/Storage';
import { useWorkspace } from '@/hooks/useWorkspace';

export default function AdminSystem() {
    const { pageData } = useWorkspace();
    const stats = pageData.stats || [];

    return (
        <div className="page-stack">
            <PageHeader
                eyebrow="Super Admin"
                title="System"
                description="Review platform health and configure system-level settings."
            />
            <div className="grid-three">
                {stats.slice(0, 3).map((item) => (
                    <MetricCard key={item.label} label={item.label} value={item.value} note={item.trend} />
                ))}
            </div>
            <Card title="File Storage">
                <div className="card-pad">
                    <StorageSettingsSection />
                </div>
            </Card>
        </div>
    );
}
