import React, { useState } from 'react';
import { Briefcase, Building2, Globe, Link2, Mail, MapPin, Phone, Save, User } from 'lucide-react';
import ImageUpload from '@/components/forms/ImageUpload';
import Input from '@/components/forms/Input';
import Select from '@/components/forms/Select';
import TagInput from '@/components/forms/TagInput';
import Textarea from '@/components/forms/Textarea';
import Button from '@/components/ui/Button';
import ErrorState from '@/components/ui/ErrorState';
import Modal from '@/components/ui/Modal';
import { createCompetitor, removeCompetitorLogo, updateCompetitor, uploadCompetitorLogo } from '@/api/competitors';
import { useWorkspace } from '@/hooks/useWorkspace';
import { SOCIAL_NETWORKS } from '@/pages/clients/components/competitors/socialNetworks';

function textValue(form, name) {
    return String(form.get(name) || '').trim() || null;
}

function Section({ title, children }) {
    return (
        <div className="form-section">
            <h3>{title}</h3>
            {children}
        </div>
    );
}

export default function CompetitorFormModal({ client, competitor = null, maxKeywords = null, onClose, onSaved }) {
    const { options, workspaceSlug } = useWorkspace();
    const [keywords, setKeywords] = useState(competitor?.keywords || []);
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState({});
    const [formError, setFormError] = useState(null);
    const isEditing = Boolean(competitor?.id);
    const error = (key) => errors[key]?.[0];

    function payloadFrom(form) {
        return {
            competitor_name: form.get('competitor_name'),
            competitor_domain: form.get('competitor_domain'),
            competitor_type: form.get('competitor_type') || null,
            tier: form.get('tier'),
            industry: textValue(form, 'industry'),
            location: textValue(form, 'location'),
            description: textValue(form, 'description'),
            notes: textValue(form, 'notes'),
            contact: {
                name: textValue(form, 'contact_name'),
                email: textValue(form, 'contact_email'),
                phone: textValue(form, 'contact_phone'),
            },
            social_media: Object.fromEntries(SOCIAL_NETWORKS.map(({ key }) => [key, textValue(form, `social_${key}`)])),
            keywords,
        };
    }

    /**
     * The logo is sent separately once the competitor exists. If only the logo
     * step fails the competitor is still saved, so the modal closes and the
     * problem is reported rather than inviting a duplicate submission.
     */
    async function saveLogo(saved, form) {
        const logo = form.get('logo');

        try {
            if (logo instanceof File && logo.size > 0) {
                return { competitor: (await uploadCompetitorLogo(workspaceSlug, client.id, saved.id, logo)).data };
            }

            if (form.get('remove_logo') === '1' && saved.logo_url) {
                return { competitor: (await removeCompetitorLogo(workspaceSlug, client.id, saved.id)).data };
            }
        } catch (logoError) {
            return { competitor: saved, logoError: logoError.errors?.logo?.[0] || logoError.message };
        }

        return { competitor: saved };
    }

    async function handleSubmit(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        setFormError(null);

        const form = new FormData(event.currentTarget);

        try {
            const response = isEditing
                ? await updateCompetitor(workspaceSlug, client.id, competitor.id, payloadFrom(form))
                : await createCompetitor(workspaceSlug, client.id, payloadFrom(form));
            const { competitor: saved, logoError } = await saveLogo(response.data, form);
            const message = isEditing ? 'Competitor updated successfully.' : 'Competitor added successfully.';

            onSaved(saved, logoError ? `${message} The logo could not be saved: ${logoError}` : message, logoError ? 'error' : 'success');
        } catch (saveError) {
            setErrors(saveError.errors);
            setFormError(saveError.errors?.competitors?.[0] || saveError.message);
            setSubmitting(false);
        }
    }

    return (
        <Modal title={isEditing ? 'Edit competitor' : 'Add competitor'} onClose={onClose} busy={submitting} wide>
            <form className="form-stack" onSubmit={handleSubmit}>
                {formError ? <ErrorState title="Could not save competitor" copy={formError} /> : null}
                <Section title="Basic information">
                    <div className="form-row">
                        <Input icon={Building2} name="competitor_name" label="Competitor name *" defaultValue={competitor?.competitor_name || ''} error={error('competitor_name')} required />
                        <Input icon={Globe} name="competitor_domain" label="Website / domain *" defaultValue={competitor?.competitor_domain || ''} placeholder="example.com" error={error('competitor_domain')} required />
                    </div>
                    <div className="form-row">
                        <Select
                            name="competitor_type"
                            label="Competitor type"
                            defaultValue={competitor?.competitor_type || ''}
                            options={[{ value: '', label: 'Not specified' }, ...(options.competitor_types || [])]}
                            error={error('competitor_type')}
                        />
                        <Select name="tier" label="Tier *" defaultValue={competitor?.tier || 'primary'} options={options.competitor_tiers || []} error={error('tier')} />
                    </div>
                </Section>
                <Section title="Business information">
                    <div className="form-row">
                        <Input icon={Briefcase} name="industry" label="Industry" defaultValue={competitor?.industry || ''} error={error('industry')} />
                        <Input icon={MapPin} name="location" label="Location" defaultValue={competitor?.location || ''} error={error('location')} />
                    </div>
                    <Textarea name="description" label="Description" defaultValue={competitor?.description || ''} rows={4} error={error('description')} />
                    <Textarea name="notes" label="Notes" defaultValue={competitor?.notes || ''} rows={4} error={error('notes')} />
                </Section>
                <Section title="Contact">
                    <div className="form-row">
                        <Input icon={User} name="contact_name" label="Name" defaultValue={competitor?.contact?.name || ''} error={error('contact.name')} />
                        <Input icon={Mail} name="contact_email" type="email" label="Email" defaultValue={competitor?.contact?.email || ''} error={error('contact.email')} />
                    </div>
                    <div className="form-row">
                        <Input icon={Phone} name="contact_phone" type="tel" label="Phone" defaultValue={competitor?.contact?.phone || ''} error={error('contact.phone')} />
                    </div>
                </Section>
                <Section title="Online presence">
                    <div className="form-row">
                        {SOCIAL_NETWORKS.map(({ key, label }) => (
                            <Input
                                key={key}
                                icon={Link2}
                                name={`social_${key}`}
                                type="url"
                                label={label}
                                placeholder="https://"
                                defaultValue={competitor?.social_media?.[key] || ''}
                                error={error(`social_media.${key}`)}
                            />
                        ))}
                    </div>
                </Section>
                <Section title="SEO">
                    <TagInput
                        label="Keywords"
                        values={keywords}
                        onChange={setKeywords}
                        max={maxKeywords}
                        hint="Press Enter or type a comma to add a keyword. Click a keyword to edit it."
                        error={error('keywords') || Object.entries(errors).find(([key]) => key.startsWith('keywords.'))?.[1]?.[0]}
                    />
                </Section>
                <Section title="Logo">
                    <ImageUpload currentUrl={competitor?.logo_url || null} currentLabel="Current competitor logo" />
                </Section>
                <div className="inline-actions action-group-end">
                    <Button variant="secondary" onClick={onClose} disabled={submitting}>Cancel</Button>
                    <Button icon={Save} type="submit" disabled={submitting}>{submitting ? 'Saving...' : 'Save competitor'}</Button>
                </div>
            </form>
        </Modal>
    );
}
