import React, { useState } from 'react';
import { Tag } from 'lucide-react';
import Checkbox from '@/components/forms/Checkbox';
import ImageUpload from '@/components/forms/ImageUpload';
import Input from '@/components/forms/Input';
import ColourInput from '@/components/forms/ColourInput';

export default function WorkspaceGeneralFields({ defaults, errors }) {
    const [name, setName] = useState(defaults.name);
    const [slug, setSlug] = useState(defaults.slug);
    const [slugTouched, setSlugTouched] = useState(Boolean(defaults.slug));

    function slugify(value) {
        return value
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function handleNameChange(event) {
        const nextName = event.target.value;
        setName(nextName);

        if (!slugTouched) {
            setSlug(slugify(nextName));
        }
    }

    return (
        <div className="form-section">
            <div>
                <h3>Workspace Details</h3>
                <p>Core identity, branding, and availability for this workspace.</p>
            </div>
            <div className="form-row">
                <Input icon={Tag} name="name" label="Workspace Name" value={name} onChange={handleNameChange} error={errors.name?.[0]} />
                <Input
                    icon={Tag}
                    name="slug"
                    label="Slug"
                    value={slug}
                    onChange={(event) => {
                        setSlugTouched(true);
                        setSlug(slugify(event.target.value));
                    }}
                    error={errors.slug?.[0]}
                />
            </div>
            <ImageUpload currentUrl={defaults.logo_url || null} currentLabel="Current workspace logo" error={errors.logo?.[0]} />
            <div className="form-row">
                <ColourInput name="primary_colour" label="Primary Colour" defaultValue={defaults.primary_colour} error={errors.primary_colour?.[0]} />
                <ColourInput name="accent_colour" label="Accent Colour" defaultValue={defaults.accent_colour} error={errors.accent_colour?.[0]} />
            </div>
            <div className="switch-line">
                <div>
                    <strong>Active Workspace</strong>
                    <span>Allow users to access this workspace.</span>
                </div>
                <Checkbox name="is_active" defaultChecked={defaults.is_active}>On</Checkbox>
            </div>
        </div>
    );
}
