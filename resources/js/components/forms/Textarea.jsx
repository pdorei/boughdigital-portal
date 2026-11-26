import React from 'react';
import FormField from '@/components/forms/FormField';

export default function Textarea({ name, label, defaultValue = '', rows = 5, placeholder, error, value, onChange, disabled = false, hint = null }) {
    const valueProps = value === undefined ? { defaultValue } : { value, onChange };

    return (
        <FormField label={label} error={error}>
            <span className={`field-shell field-shell-textarea${error ? ' has-error' : ''}`}>
                <textarea name={name} rows={rows} placeholder={placeholder} disabled={disabled} {...valueProps} />
            </span>
            {hint ? <span className="field-hint">{hint}</span> : null}
        </FormField>
    );
}
