import React, { useMemo, useState } from 'react';
import { Mail, Save, User, X } from 'lucide-react';
import Checkbox from '@/components/forms/Checkbox';
import Input from '@/components/forms/Input';
import PasswordInput from '@/components/forms/PasswordInput';
import Select from '@/components/forms/Select';
import Button from '@/components/ui/Button';
import Card from '@/components/ui/Card';
import ErrorState from '@/components/ui/ErrorState';
import ClientAccessSelector from '@/pages/team/components/ClientAccessSelector';
import { createAdminUser, createWorkspaceUser, updateAdminUser, updateWorkspaceUser } from '@/api/users';
import { useWorkspace } from '@/hooks/useWorkspace';
import { flash } from '@/utils/flash';

export default function UserForm({ user = null, admin = false, base }) {
    const { options, pageData, workspaceSlug } = useWorkspace();
    const roleOptions = admin ? options.roles : options.workspace_roles;
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState({});
    const [formError, setFormError] = useState(null);
    const [role, setRole] = useState(user?.role || 'employee');
    const [workspaceId, setWorkspaceId] = useState(user?.workspace_id || pageData.form_workspaces?.[0]?.id || '');
    const [clientIds, setClientIds] = useState(user?.client_ids || []);
    const isEditing = Boolean(user?.id);
    const credentialsLocked = isEditing && !user.can?.change_credentials;
    const isPlatformUser = role === 'super_admin';

    const availableClients = useMemo(() => {
        const clients = pageData.form_clients || [];

        return admin ? clients.filter((client) => Number(client.workspace_id) === Number(workspaceId)) : clients;
    }, [admin, pageData.form_clients, workspaceId]);

    async function handleSubmit(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        setFormError(null);

        const form = new FormData(event.currentTarget);
        const payload = {
            name: form.get('name'),
            role,
            avatar_url: form.get('avatar_url') || null,
            is_active: form.get('is_active') === '1',
            client_ids: isPlatformUser ? [] : clientIds,
            ...(credentialsLocked ? {} : { email: form.get('email'), password: form.get('password') || null }),
            ...(admin ? { workspace_id: isPlatformUser ? null : Number(workspaceId) } : {}),
        };

        try {
            const response = admin
                ? (isEditing ? await updateAdminUser(user.id, payload) : await createAdminUser(payload))
                : (isEditing ? await updateWorkspaceUser(workspaceSlug, user.id, payload) : await createWorkspaceUser(workspaceSlug, payload));

            flash(isEditing ? 'User updated successfully.' : 'User created successfully.');
            window.location.assign(`${base}/${response.data.id}`);
        } catch (error) {
            setErrors(error.errors);
            setFormError(error.message);
            setSubmitting(false);
        }
    }

    return (
        <Card title={isEditing ? 'Edit user' : 'Create user'} padded>
            {formError ? <ErrorState title="Could not save user" copy={formError} /> : null}
            <form className="form-stack" onSubmit={handleSubmit}>
                <div className="form-row">
                    <Input icon={User} name="name" label="Name" defaultValue={user?.name || ''} error={errors.name?.[0]} required />
                    <Input icon={Mail} name="email" type="email" label="Email" defaultValue={user?.email || ''} error={errors.email?.[0]} readOnly={credentialsLocked} required />
                </div>
                {credentialsLocked ? <p className="muted text-sm">Only this admin or a Super Admin can change their email address or password.</p> : null}
                <Input icon={User} name="avatar_url" type="url" label="Avatar URL" defaultValue={user?.avatar_url || ''} error={errors.avatar_url?.[0]} />
                <div className="form-row">
                    <Select label="Role" value={role} onChange={(event) => setRole(event.target.value)} options={roleOptions || []} error={errors.role?.[0] || errors.user?.[0]} />
                    {admin ? (
                        <Select
                            label="Workspace"
                            value={workspaceId}
                            onChange={(event) => setWorkspaceId(event.target.value)}
                            options={(pageData.form_workspaces || []).map((workspace) => ({ value: workspace.id, label: workspace.name }))}
                            error={errors.workspace_id?.[0]}
                            disabled={isPlatformUser}
                        />
                    ) : null}
                </div>
                {!credentialsLocked ? (
                    <PasswordInput name="password" label={isEditing ? 'New password (optional)' : 'Temporary password (optional)'} autoComplete="new-password" error={errors.password?.[0]} />
                ) : null}
                <Checkbox name="is_active" defaultChecked={user?.is_active ?? true}>Active user</Checkbox>
                {!isPlatformUser ? (
                    <Card title="Client access">
                        <p className="card-pad muted text-sm">Workspace admins see every client. Other roles only see the clients selected here.</p>
                        <ClientAccessSelector clients={availableClients} selected={clientIds} onChange={setClientIds} />
                    </Card>
                ) : null}
                <div className="inline-actions">
                    <Button icon={Save} type="submit" disabled={submitting}>{submitting ? 'Saving...' : 'Save user'}</Button>
                    <Button icon={X} variant="secondary" href={isEditing ? `${base}/${user.id}` : base}>Cancel</Button>
                </div>
            </form>
        </Card>
    );
}
