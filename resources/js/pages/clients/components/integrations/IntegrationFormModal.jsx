import React, { useState } from 'react';
import { Link2, Save, Tag } from 'lucide-react';
import DisplayField from '@/components/forms/DisplayField';
import Input from '@/components/forms/Input';
import PasswordInput from '@/components/forms/PasswordInput';
import Button from '@/components/ui/Button';
import ErrorState from '@/components/ui/ErrorState';
import Modal from '@/components/ui/Modal';
import { createIntegration, updateIntegration } from '@/api/integrations';
import { useWorkspace } from '@/hooks/useWorkspace';

/**
 * Adds or edits one integration. Saved credentials are never sent to the
 * browser, so when editing a blank credential field keeps the stored one.
 */
export default function IntegrationFormModal({ client, provider, integration = null, onClose, onSaved }) {
    const { options, workspaceSlug } = useWorkspace();
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState({});
    const [formError, setFormError] = useState(null);
    const isEditing = Boolean(integration?.id);
    const auth = (options.integration_auth_types || []).find((type) => type.value === provider.auth_type);
    const keepsSavedCredential = isEditing && integration.credential_configured;

    async function handleSubmit(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        setFormError(null);

        const form = new FormData(event.currentTarget);
        const payload = {
            name: form.get('name') || null,
            credentials: { [auth.credential_field]: form.get('credential') || null },
        };

        try {
            const response = isEditing
                ? await updateIntegration(workspaceSlug, client.id, integration.id, payload)
                : await createIntegration(workspaceSlug, client.id, { provider: provider.value, ...payload });

            onSaved(response.data, isEditing ? 'Integration updated successfully.' : `${response.data.display_name} integration connected.`);
        } catch (error) {
            setErrors(error.errors);
            setFormError(error.errors?.provider?.[0] || error.message);
            setSubmitting(false);
        }
    }

    if (!provider.available) {
        return (
            <Modal title={`Connect ${provider.label}`} onClose={onClose} wide>
                <div className="form-stack">
                    <div className="form-row">
                        <DisplayField label="Provider">{provider.label}</DisplayField>
                        <DisplayField label="Authentication">OAuth</DisplayField>
                    </div>
                    <p className="section-copy">
                        {provider.label} connects through OAuth, where the client signs in to {provider.label} and grants access.
                        That connection flow has not been built yet, so {provider.label} cannot be connected. Nothing has been saved.
                    </p>
                    <div className="inline-actions action-group-end">
                        <Button variant="secondary" onClick={onClose}>Close</Button>
                        <Button icon={Link2} disabled>Connect {provider.label}</Button>
                    </div>
                </div>
            </Modal>
        );
    }

    return (
        <Modal title={isEditing ? `Edit ${integration.display_name}` : `Connect ${provider.label}`} onClose={onClose} busy={submitting} wide>
            <form className="form-stack" onSubmit={handleSubmit}>
                {formError ? <ErrorState title="Could not save integration" copy={formError} /> : null}
                <div className="form-row">
                    <DisplayField label="Provider">{provider.label}</DisplayField>
                    <DisplayField label="Authentication">{auth?.label}</DisplayField>
                </div>
                <Input
                    icon={Tag}
                    name="name"
                    label="Account name (optional)"
                    placeholder={`For example, Main ${provider.label} account`}
                    defaultValue={integration?.name || ''}
                    error={errors.name?.[0]}
                />
                <PasswordInput
                    name="credential"
                    label={`${auth?.label} *`}
                    autoComplete="new-password"
                    placeholder={keepsSavedCredential ? 'Saved. Leave blank to keep the current value.' : ''}
                    required={!keepsSavedCredential}
                    error={errors[`credentials.${auth?.credential_field}`]?.[0]}
                />
                <p className="field-hint">Encrypted before it is stored. It is never shown again after saving.</p>
                <div className="inline-actions action-group-end">
                    <Button variant="secondary" onClick={onClose} disabled={submitting}>Cancel</Button>
                    <Button icon={Save} type="submit" disabled={submitting}>{submitting ? 'Saving...' : 'Save Integration'}</Button>
                </div>
            </form>
        </Modal>
    );
}
