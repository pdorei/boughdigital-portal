import React, { useMemo, useState } from 'react';
import { Save, X } from 'lucide-react';
import Button from '@/components/ui/Button';
import Card from '@/components/ui/Card';
import ErrorState from '@/components/ui/ErrorState';
import { createWorkspace, updateWorkspace } from '@/api/workspaces';
import { useWorkspace } from '@/hooks/useWorkspace';
import { flash } from '@/utils/flash';
import WorkspaceDateTimeFields from '@/pages/admin/workspaces/components/WorkspaceDateTimeFields';
import WorkspaceGeneralFields from '@/pages/admin/workspaces/components/WorkspaceGeneralFields';

export default function WorkspaceForm({ workspace = null }) {
    const { datePreferenceOptions } = useWorkspace();
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState({});
    const [formError, setFormError] = useState(null);
    const isEditing = Boolean(workspace?.id);
    const title = isEditing ? 'Edit Workspace' : 'Create Workspace';

    const defaults = useMemo(() => ({
        name: workspace?.name || '',
        slug: workspace?.slug || '',
        logo_url: workspace?.logo_url || '',
        primary_colour: workspace?.primary_colour || '#14232d',
        accent_colour: workspace?.accent_colour || '#8fcf00',
        is_active: workspace?.is_active ?? true,
    }), [workspace]);

    const dateTimeSettings = useMemo(() => ({
        timezone: workspace?.settings?.timezone || '',
        date_format: workspace?.settings?.date_format || '',
        datetime_format: workspace?.settings?.datetime_format || '',
    }), [workspace]);

    async function handleSubmit(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        setFormError(null);

        const form = new FormData(event.currentTarget);
        const settings = { ...(workspace?.settings || {}) };

        ['timezone', 'date_format', 'datetime_format'].forEach((key) => {
            settings[key] = form.get(key) || null;
        });

        form.set('settings[timezone]', settings.timezone || '');
        form.set('settings[date_format]', settings.date_format || '');
        form.set('settings[datetime_format]', settings.datetime_format || '');
        form.set('is_active', form.get('is_active') === '1' ? '1' : '0');

        const logo = form.get('logo');
        if (!(logo instanceof File) || logo.size === 0) {
            form.delete('logo');
        }

        try {
            const response = isEditing ? await updateWorkspace(workspace.id, form) : await createWorkspace(form);
            const saved = response.data;
            flash(isEditing ? 'Workspace updated successfully.' : 'Workspace created successfully.');
            window.location.assign(`/admin/workspaces/${saved.id}`);
        } catch (error) {
            setErrors(error.errors);
            setFormError(error.message);
            setSubmitting(false);
        }
    }

    return (
        <Card title={title} padded>
            {formError ? <ErrorState title="Could not save workspace" copy={formError} /> : null}
            <form className="form-stack" onSubmit={handleSubmit}>
                <WorkspaceGeneralFields defaults={defaults} errors={errors} />
                <WorkspaceDateTimeFields settings={dateTimeSettings} options={datePreferenceOptions} errors={errors} />
                <div className="inline-actions">
                    <Button icon={Save} type="submit" disabled={submitting}>{submitting ? 'Saving...' : 'Save workspace'}</Button>
                    <Button icon={X} variant="secondary" href="/admin/workspaces">Cancel</Button>
                </div>
            </form>
        </Card>
    );
}
