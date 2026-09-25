import React, { useState } from 'react';
import { FileText, Link2, Save, Type } from 'lucide-react';
import FormField from '@/components/forms/FormField';
import Input from '@/components/forms/Input';
import Select from '@/components/forms/Select';
import Button from '@/components/ui/Button';
import ErrorState from '@/components/ui/ErrorState';
import Modal from '@/components/ui/Modal';
import { linkDocument, updateDocument, uploadDocument } from '@/api/documents';
import { useWorkspace } from '@/hooks/useWorkspace';

const TITLES = { upload: 'Upload document', link: 'Link external document', edit: 'Edit document details' };

/**
 * mode: "upload" | "link" | "edit". Editing changes metadata only; an
 * uploaded file is replaced by uploading a new document.
 */
export default function DocumentFormModal({ mode, document = null, clients = [], defaultClientId = null, onClose, onSaved }) {
    const { options, workspaceSlug } = useWorkspace();
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState({});
    const [formError, setFormError] = useState(null);
    const isLink = mode === 'link' || (mode === 'edit' && !document?.download_url);

    async function save(form) {
        if (mode === 'upload') return uploadDocument(workspaceSlug, form);

        const payload = Object.fromEntries(form.entries());

        return mode === 'link' ? linkDocument(workspaceSlug, payload) : updateDocument(workspaceSlug, document.id, payload);
    }

    async function handleSubmit(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        setFormError(null);

        try {
            const response = await save(new FormData(event.currentTarget));
            onSaved(response.data, mode === 'edit' ? 'Document updated successfully.' : 'Document added successfully.');
        } catch (error) {
            setErrors(error.errors);
            setFormError(error.message);
            setSubmitting(false);
        }
    }

    return (
        <Modal title={TITLES[mode]} onClose={onClose} busy={submitting} wide>
            <form className="form-stack" onSubmit={handleSubmit}>
                {formError ? <ErrorState title="Could not save document" copy={formError} /> : null}
                {mode !== 'edit' ? (
                    <Select name="client_id" label="Client" defaultValue={defaultClientId ?? clients[0]?.value ?? ''} options={clients} error={errors.client_id?.[0]} />
                ) : null}
                {mode === 'upload' ? (
                    <FormField label="File" error={errors.file?.[0]}>
                        <span className={`field-shell${errors.file?.[0] ? ' has-error' : ''}`}>
                            <FileText size={17} aria-hidden="true" />
                            <input name="file" type="file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt,.png,.jpg,.jpeg,.webp" />
                        </span>
                    </FormField>
                ) : null}
                {isLink ? (
                    <Input icon={Link2} name="external_url" type="url" label="Document URL" defaultValue={document?.external_url || ''} error={errors.external_url?.[0]} required placeholder="https://docs.google.com/..." />
                ) : null}
                <Input
                    icon={Type}
                    name="title"
                    label={mode === 'upload' ? 'Title (optional)' : 'Title'}
                    defaultValue={document?.title || ''}
                    error={errors.title?.[0]}
                    required={mode !== 'upload'}
                    placeholder={mode === 'upload' ? 'Defaults to the file name' : undefined}
                />
                <div className="form-row">
                    <Select name="folder" label="Folder" defaultValue={document?.folder || 'other'} options={options.document_folders || []} error={errors.folder?.[0]} />
                    <Input icon={FileText} name="document_type" label="Document type" defaultValue={document?.document_type || ''} error={errors.document_type?.[0]} placeholder="e.g. Proposal, Audit, Brief" />
                </div>
                {isLink ? <p className="muted text-sm">The link is stored as a reference. The portal does not read or sync the document's contents.</p> : null}
                <div className="inline-actions action-group-end">
                    <Button variant="secondary" onClick={onClose} disabled={submitting}>Cancel</Button>
                    <Button icon={Save} type="submit" disabled={submitting}>{submitting ? 'Saving...' : 'Save document'}</Button>
                </div>
            </form>
        </Modal>
    );
}
