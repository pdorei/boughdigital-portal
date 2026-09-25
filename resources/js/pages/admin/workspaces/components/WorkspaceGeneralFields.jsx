import React, { useMemo, useState } from 'react';
import { Image, Tag, Trash2, Upload } from 'lucide-react';
import Checkbox from '@/components/forms/Checkbox';
import Input from '@/components/forms/Input';
import Button from '@/components/ui/Button';
import ColourInput from '@/pages/admin/workspaces/components/ColourInput';

export default function WorkspaceGeneralFields({ defaults, errors }) {
    const [name, setName] = useState(defaults.name);
    const [slug, setSlug] = useState(defaults.slug);
    const [slugTouched, setSlugTouched] = useState(Boolean(defaults.slug));
    const [preview, setPreview] = useState(defaults.logo_url || null);
    const [fileName, setFileName] = useState('');
    const [removeLogo, setRemoveLogo] = useState(false);

    const logoLabel = useMemo(() => {
        if (fileName) return fileName;
        if (removeLogo || !defaults.logo_url) return 'No logo uploaded';

        return 'Current workspace logo';
    }, [defaults.logo_url, fileName, removeLogo]);

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

    function handleLogoChange(event) {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        setFileName(file.name);
        setPreview(URL.createObjectURL(file));
        setRemoveLogo(false);
    }

    function handleRemoveLogo() {
        setPreview(null);
        setFileName('');
        setRemoveLogo(true);
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
            <div className="logo-upload">
                <div className="logo-preview">
                    {preview ? <img src={preview} alt="" /> : <span className="logo-placeholder"><Image size={18} /></span>}
                    <span>{logoLabel}</span>
                </div>
                <div className="inline-actions">
                    <label className="btn btn-secondary">
                        <Upload size={17} />
                        {defaults.logo_url ? 'Replace Logo' : 'Choose Logo'}
                        <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" onChange={handleLogoChange} />
                    </label>
                    {defaults.logo_url && !removeLogo ? (
                        <Button icon={Trash2} variant="secondary" type="button" onClick={handleRemoveLogo}>Remove Logo</Button>
                    ) : null}
                </div>
                <input type="hidden" name="remove_logo" value={removeLogo ? '1' : '0'} />
                {errors.logo?.[0] ? <span className="field-error">{errors.logo[0]}</span> : null}
            </div>
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
