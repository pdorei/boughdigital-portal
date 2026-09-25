import React, { useState } from 'react';
import { Edit, Trash2 } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import Card from '@/components/ui/Card';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import ErrorState from '@/components/ui/ErrorState';
import StatsGrid from '@/components/ui/StatsGrid';
import PageHeader from '@/components/layout/PageHeader';
import { deleteWorkspace } from '@/api/workspaces';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { useWorkspace } from '@/hooks/useWorkspace';
import { flash } from '@/utils/flash';
import { optionLabel } from '@/utils/options';

function SimpleList({ items, empty, describe }) {
    if (!items.length) {
        return <div className="card-pad muted">{empty}</div>;
    }

    return (
        <div className="list">
            {items.map((item) => (
                <div className="list-row" key={item.id}>
                    <strong>{item.name}</strong>
                    <span className="muted">{describe(item)}</span>
                </div>
            ))}
        </div>
    );
}

function deletionBlocker(workspace) {
    if (workspace.is_active) return 'Deactivate the workspace before it can be deleted.';
    if (workspace.clients_count || workspace.tasks_count || workspace.documents_count) {
        return 'This workspace has clients, tasks or documents, so it is kept deactivated to preserve its history.';
    }

    return null;
}

export default function WorkspaceShow() {
    const { options, pageData } = useWorkspace();
    const { formatDateTime } = useDateFormatter();
    const workspace = pageData.workspace;
    const [confirmation, setConfirmation] = useState('');
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [deleteError, setDeleteError] = useState(null);
    const blocker = deletionBlocker(workspace);

    async function handleDelete() {
        setDeleting(true);
        setDeleteError(null);

        try {
            await deleteWorkspace(workspace.id, confirmation);
            flash(`Workspace ${workspace.name} deleted.`);
            window.location.assign('/admin/workspaces');
        } catch (error) {
            setDeleteError(error.message);
            setConfirmingDelete(false);
            setDeleting(false);
        }
    }

    return (
        <div className="page-stack">
            <PageHeader
                eyebrow="Workspace"
                title={workspace.name}
                description={`/${workspace.slug} · created ${formatDateTime(workspace.created_at)}`}
                action={<Button icon={Edit} variant="secondary" href={`/admin/workspaces/${workspace.id}/edit`}>Edit workspace</Button>}
            />
            <StatsGrid stats={[
                { label: 'Members', value: workspace.users_count },
                { label: 'Clients', value: workspace.clients_count },
                { label: 'Tasks', value: workspace.tasks_count },
                { label: 'Documents', value: workspace.documents_count },
            ]} />
            <div className="grid-two">
                <Card title="Branding" padded>
                    <div className="list">
                        <div className="logo-preview">
                            {workspace.logo_url ? <img src={workspace.logo_url} alt={`${workspace.name} logo`} /> : <span className="logo-placeholder">No logo</span>}
                            <span>{workspace.logo_url ? 'Current workspace logo' : 'No logo uploaded'}</span>
                        </div>
                        <div className="list-row"><strong>Primary colour</strong><span className="muted">{workspace.primary_colour || 'Not set'}</span></div>
                        <div className="list-row"><strong>Accent colour</strong><span className="muted">{workspace.accent_colour || 'Not set'}</span></div>
                        <div className="list-row"><strong>Status</strong><Badge status={workspace.is_active ? 'active' : 'on_hold'}>{workspace.is_active ? 'Active' : 'Inactive'}</Badge></div>
                    </div>
                </Card>
                <Card title="Members">
                    <SimpleList items={workspace.users} empty="No members yet." describe={(user) => `${user.email} · ${optionLabel(options.roles, user.role)}`} />
                </Card>
            </div>
            <Card title="Clients">
                <SimpleList items={workspace.clients} empty="No clients in this workspace yet." describe={(client) => optionLabel(options.client_statuses, client.status)} />
            </Card>
            <Card title="Danger zone" padded>
                <div className="danger-zone">
                    <div>
                        <h3>Delete workspace</h3>
                        <p>Permanent deletion is only for workspaces created by mistake. Members keep their accounts and the activity history is retained.</p>
                    </div>
                    {blocker ? <p className="muted">{blocker}</p> : (
                        <>
                            <label className="field-label" htmlFor="workspace-delete-confirmation">Type <strong>{workspace.slug}</strong> to confirm</label>
                            <span className="field-shell">
                                <input id="workspace-delete-confirmation" type="text" value={confirmation} onChange={(event) => setConfirmation(event.target.value)} />
                            </span>
                            {deleteError ? <ErrorState title="Could not delete workspace" copy={deleteError} /> : null}
                            <div className="inline-actions">
                                <Button icon={Trash2} variant="danger" disabled={deleting || confirmation !== workspace.slug} onClick={() => setConfirmingDelete(true)}>
                                    Delete workspace
                                </Button>
                            </div>
                        </>
                    )}
                </div>
            </Card>
            {confirmingDelete ? (
                <ConfirmDialog
                    title={`Delete ${workspace.name}?`}
                    description="The workspace and its logo are removed permanently. This cannot be undone."
                    confirmLabel="Delete workspace"
                    loading={deleting}
                    onCancel={() => setConfirmingDelete(false)}
                    onConfirm={handleDelete}
                />
            ) : null}
        </div>
    );
}
