import React from 'react';
import FormField from '@/components/forms/FormField';

export default function Textarea({ name, label, defaultValue = '', rows = 4, error }) {
    return (
        <FormField label={label} error={error}>
            <span className={`field-shell h-auto${error ? ' has-error' : ''}`}>
                <textarea name={name} defaultValue={defaultValue} rows={rows} />
            </span>
        </FormField>
    );
}
