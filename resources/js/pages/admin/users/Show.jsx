import React from 'react';
import { Edit } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import Card from '@/components/ui/Card';
import StatsGrid from '@/components/ui/StatsGrid';
import PageHeader from '@/components/layout/PageHeader';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { useWorkspace } from '@/hooks/useWorkspace';
import { optionLabel } from '@/utils/options';

export default function UserShow({ base = '/admin/users' }) {
    const { options, pageData } = useWorkspace();
    const { formatDateTime } = useDateFormatter();
    const user = pageData.selected_user;

    return (
        <div className="page-stack">
            <PageHeader
                eyebrow="User"
                title={user.name}
                description={user.email}
                action={user.can?.update ? <Button icon={Edit} variant="secondary" href={`${base}/${user.id}/edit`}>Edit user</Button> : null}
            />
            <StatsGrid stats={[
                { label: 'Workspace', value: user.workspace?.name || 'Platform' },
                { label: 'Role', value: optionLabel(options.roles, user.role) },
                { label: 'Client access', value: user.client_ids?.length ?? 0, trend: 'Explicit assignments' },
            ]} />
            <Card title="Account" padded>
                <div className="list">
                    <div className="list-row"><strong>Status</strong><Badge status={user.is_active ? 'active' : 'on_hold'}>{user.is_active ? 'Active' : 'Inactive'}</Badge></div>
                    <div className="list-row"><strong>Last sign-in</strong><span className="muted">{formatDateTime(user.last_login, 'Never')}</span></div>
                    <div className="list-row"><strong>Created</strong><span className="muted">{formatDateTime(user.created_at)}</span></div>
                </div>
            </Card>
        </div>
    );
}
