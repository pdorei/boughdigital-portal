import React, { useEffect, useState } from 'react';
import { Image, Trash2, Upload } from 'lucide-react';
import Button from '@/components/ui/Button';

/**
 * Logo/image picker with a preview. Submits the file as `name` and a
 * `removeName` flag ("1") when the current image should be removed.
 */
export default function ImageUpload({
    name = 'logo',
    removeName = 'remove_logo',
    currentUrl = null,
    currentLabel = 'Current logo',
    emptyLabel = 'No logo uploaded',
    error,
}) {
    const [preview, setPreview] = useState(currentUrl);
    const [fileName, setFileName] = useState('');
    const [removed, setRemoved] = useState(false);

    useEffect(() => () => {
        if (preview?.startsWith('blob:')) URL.revokeObjectURL(preview);
    }, [preview]);

    function handleChange(event) {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        setFileName(file.name);
        setPreview(URL.createObjectURL(file));
        setRemoved(false);
    }

    function handleRemove() {
        setPreview(null);
        setFileName('');
        setRemoved(true);
    }

    const label = fileName || (removed || !currentUrl ? emptyLabel : currentLabel);

    return (
        <div className="logo-upload">
            <div className="logo-preview">
                {preview ? <img src={preview} alt="" /> : <span className="logo-placeholder"><Image size={18} /></span>}
                <span>{label}</span>
            </div>
            <div className="inline-actions">
                <label className="btn btn-secondary">
                    <Upload size={17} />
                    {currentUrl ? 'Replace Logo' : 'Choose Logo'}
                    <input type="file" name={name} accept="image/png,image/jpeg,image/webp" onChange={handleChange} />
                </label>
                {currentUrl && !removed ? (
                    <Button icon={Trash2} variant="secondary" type="button" onClick={handleRemove}>Remove Logo</Button>
                ) : null}
            </div>
            <input type="hidden" name={removeName} value={removed ? '1' : '0'} />
            {error ? <span className="field-error">{error}</span> : null}
        </div>
    );
}
