import React from 'react';
import Badge from '@/components/ui/Badge';
import Card from '@/components/ui/Card';
import StatsGrid from '@/components/ui/StatsGrid';
import Table from '@/components/ui/Table';
import PageHeader from '@/components/layout/PageHeader';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { useWorkspace } from '@/hooks/useWorkspace';

export default function AdminDashboard({ title }) {
    const { pageData } = useWorkspace();
    const { formatDateTime } = useDateFormatter();
    const workspaces = pageData.workspaces || [];

    return (
        <div className="page-stack">
            <PageHeader eyebrow="Platform overview" title={title} description="Monitor workspace health, users, client volume, and platform operations." />
            <StatsGrid stats={pageData.stats || []} />
            <div className="grid-two">
                <Card title="Recent Activity">
                    <div className="list">
                        {(pageData.recent_activity || []).map((activity, index) => (
                            <div className="list-row" key={`${activity.action}-${index}`}>
                                <strong>{activity.action}</strong>
                                <span className="muted">{activity.workspace} / {activity.source} / {formatDateTime(activity.created_at, 'recently')}</span>
                            </div>
                        ))}
                        {!(pageData.recent_activity || []).length ? <div className="card-pad muted">No activity has been recorded yet.</div> : null}
                    </div>
                </Card>
                <Card title="Operational Warnings" padded>
                    <div className="list">
                        {(pageData.integration_warnings || []).map((integration, index) => (
                            <div className="list-row" key={`${integration.provider}-${index}`}>
                                <strong>{integration.provider} / {integration.status}</strong>
                                <span className="muted">{integration.workspace} / {integration.client} / {formatDateTime(integration.updated_at, 'recently')}</span>
                            </div>
                        ))}
                        {!(pageData.integration_warnings || []).length ? <div className="list-row"><strong>No integration warnings</strong><span className="muted">Connected services have not reported tracked warning states.</span></div> : null}
                    </div>
                </Card>
            </div>
            <Card title="Workspaces" action={<Badge status="active">{workspaces.length} active</Badge>}>
                <Table
                    columns={[
                        { key: 'name', label: 'Name', render: (workspace) => <span className="strong-link">{workspace.name}</span> },
                        { key: 'users_count', label: 'Users' },
                        { key: 'clients_count', label: 'Clients' },
                        { key: 'documents_count', label: 'Documents' },
                        { key: 'tasks_count', label: 'Tasks' },
                        { key: 'storage', label: 'Storage' },
                        { key: 'is_active', label: 'Status', render: (workspace) => <Badge status={workspace.is_active ? 'active' : 'on_hold'}>{workspace.is_active ? 'active' : 'inactive'}</Badge> },
                    ]}
                    rows={workspaces}
                    rowKey={(workspace) => workspace.slug}
                />
            </Card>
        </div>
    );
}
