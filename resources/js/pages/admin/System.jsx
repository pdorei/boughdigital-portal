import React from 'react';
import { SlidersHorizontal } from 'lucide-react';
import Button from '@/components/ui/Button';
import Card from '@/components/ui/Card';
import MetricCard from '@/components/ui/MetricCard';
import PageHeader from '@/components/layout/PageHeader';
import { useWorkspace } from '@/hooks/useWorkspace';

export default function AdminSystem() {
    const { pageData } = useWorkspace();
    const stats = pageData.stats || [];

    return (
        <div className="page-stack">
            <PageHeader
                eyebrow="Super Admin"
                title="System"
                description="Platform health at a glance."
            />
            <div className="grid-stats">
                {stats.map((item) => (
                    <MetricCard key={item.label} label={item.label} value={item.value} note={item.trend} />
                ))}
            </div>
            <Card title="Configuration">
                <div className="card-pad between-row">
                    <p className="section-copy">File storage, service connections and the Upwork automation are configured in Settings.</p>
                    <Button variant="secondary" icon={SlidersHorizontal} href="/admin/settings">Open settings</Button>
                </div>
            </Card>
        </div>
    );
}
