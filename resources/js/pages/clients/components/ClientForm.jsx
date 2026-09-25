import React, { useState } from 'react';
import { Globe2, Link2, Save, User, X } from 'lucide-react';
import Input from '@/components/forms/Input';
import Select from '@/components/forms/Select';
import Button from '@/components/ui/Button';
import ErrorState from '@/components/ui/ErrorState';
import { createClient, updateClient } from '@/api/clients';
import { useWorkspace } from '@/hooks/useWorkspace';
import { flash } from '@/utils/flash';

export default function ClientForm({ client = {}, base }) {
    const { options, workspaceSlug } = useWorkspace();
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState({});
    const [formError, setFormError] = useState(null);
    const isEditing = Boolean(client.id);

    async function handleSubmit(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        setFormError(null);

        const form = new FormData(event.currentTarget);
        const payload = {
            name: form.get('name'),
            slug: form.get('slug') || null,
            website_url: form.get('website_url') || null,
            status: form.get('status'),
        };

        try {
            const response = isEditing
                ? await updateClient(workspaceSlug, client.id, payload)
                : await createClient(workspaceSlug, payload);

            flash(isEditing ? 'Client updated successfully.' : 'Client created successfully.');
            window.location.assign(`${base}/clients/${response.data.slug}`);
        } catch (error) {
            setErrors(error.errors);
            setFormError(error.message);
            setSubmitting(false);
        }
    }

    return (
        <form className="form-stack" onSubmit={handleSubmit}>
            {formError ? <ErrorState title="Could not save client" copy={formError} /> : null}
            <Input icon={User} name="name" label="Client name" defaultValue={client.name || ''} error={errors.name?.[0]} required />
            <Input icon={Link2} name="slug" label="URL slug (optional)" defaultValue={client.slug || ''} error={errors.slug?.[0]} placeholder="Generated from the name" />
            <Input icon={Globe2} name="website_url" type="url" label="Website URL" defaultValue={client.website_url || ''} error={errors.website_url?.[0]} />
            <Select name="status" label="Status" defaultValue={client.status || 'active'} options={options.client_statuses || []} error={errors.status?.[0]} />
            <div className="inline-actions">
                <Button icon={Save} type="submit" disabled={submitting}>{submitting ? 'Saving...' : 'Save client'}</Button>
                <Button icon={X} variant="secondary" href={isEditing ? `${base}/clients/${client.slug}` : `${base}/clients`}>Cancel</Button>
            </div>
        </form>
    );
}
