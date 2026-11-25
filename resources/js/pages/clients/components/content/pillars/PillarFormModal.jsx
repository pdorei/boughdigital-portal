import React, { useState } from 'react';
import { Layers, Save } from 'lucide-react';
import ColourInput from '@/components/forms/ColourInput';
import Input from '@/components/forms/Input';
import Textarea from '@/components/forms/Textarea';
import Button from '@/components/ui/Button';
import ErrorState from '@/components/ui/ErrorState';
import Modal from '@/components/ui/Modal';
import { createPillar, updatePillar } from '@/api/content';
import { useWorkspace } from '@/hooks/useWorkspace';

export default function PillarFormModal({ client, pillar = null, onClose, onSaved }) {
    const { workspaceSlug } = useWorkspace();
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState({});
    const [formError, setFormError] = useState(null);
    const isEditing = Boolean(pillar?.id);

    async function handleSubmit(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        setFormError(null);

        const form = new FormData(event.currentTarget);
        const payload = {
            name: form.get('name'),
            description: form.get('description') || null,
            color: form.get('color') || null,
        };

        try {
            const response = isEditing
                ? await updatePillar(workspaceSlug, client.id, pillar.id, payload)
                : await createPillar(workspaceSlug, client.id, payload);
            onSaved(response.data, isEditing ? 'Pillar updated successfully.' : 'Pillar created successfully.');
        } catch (error) {
            setErrors(error.errors);
            setFormError(error.message);
            setSubmitting(false);
        }
    }

    return (
        <Modal title={isEditing ? 'Edit pillar' : 'Add pillar'} onClose={onClose} busy={submitting} wide>
            <form className="form-stack" onSubmit={handleSubmit}>
                {formError ? <ErrorState title="Could not save pillar" copy={formError} /> : null}
                <div className="form-row">
                    <Input icon={Layers} name="name" label="Name *" defaultValue={pillar?.name || ''} placeholder="For example, Small Business" error={errors.name?.[0]} required />
                    <ColourInput name="color" label="Colour" defaultValue={pillar?.color || '#8fcf00'} error={errors.color?.[0]} />
                </div>
                <Textarea name="description" label="Description" rows={3} defaultValue={pillar?.description || ''} error={errors.description?.[0]} />
                <div className="inline-actions action-group-end">
                    <Button variant="secondary" onClick={onClose} disabled={submitting}>Cancel</Button>
                    <Button icon={Save} type="submit" disabled={submitting}>{submitting ? 'Saving...' : 'Save pillar'}</Button>
                </div>
            </form>
        </Modal>
    );
}
