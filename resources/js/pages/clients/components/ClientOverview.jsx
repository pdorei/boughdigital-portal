import React from 'react';
import Badge from '@/components/ui/Badge';
import Card from '@/components/ui/Card';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import ActivityFeed from '@/pages/activity/components/ActivityFeed';
import TaskManager from '@/pages/tasks/components/TaskManager';

export default function ClientOverview({ client, team, base, formOptions }) {
    const { formatDate } = useDateFormatter();

    return (
        <div className="grid-two card-pad">
            <div className="form-stack">
                <Card title="Details">
                    <div className="list">
                        <div className="list-row"><strong>Status</strong><span><Badge status={client.status}>{client.status}</Badge></span></div>
                        <div className="list-row">
                            <strong>Website</strong>
                            <span className="muted">{client.website_url ? <a className="link" href={client.website_url} target="_blank" rel="noopener noreferrer">{client.website_url}</a> : 'Not set'}</span>
                        </div>
                        <div className="list-row"><strong>Open tasks</strong><span className="muted">{client.open_tasks_count} of {client.tasks_count}</span></div>
                        <div className="list-row"><strong>Documents</strong><span className="muted">{client.documents_count}</span></div>
                        <div className="list-row"><strong>Client since</strong><span className="muted">{formatDate(client.created_at)}</span></div>
                    </div>
                </Card>
                <Card title="Assigned team">
                    <div className="list">
                        {team.length ? team.map((member) => (
                            <div className="list-row" key={member.id}><strong>{member.name}</strong><span className="muted">{member.role}</span></div>
                        )) : <div className="list-row"><span className="muted">Only workspace admins can access this client.</span></div>}
                    </div>
                </Card>
            </div>
            <div className="form-stack">
                <Card title="Open tasks">
                    <TaskManager base={base} formOptions={formOptions} fixedFilters={{ client_id: client.id, open: 1 }} showClient={false} perPage={5} emptyCopy="No open tasks for this client." />
                </Card>
                <Card title="Recent activity">
                    <ActivityFeed base={base} clientId={client.id} perPage={5} paginate={false} />
                </Card>
            </div>
        </div>
    );
}
