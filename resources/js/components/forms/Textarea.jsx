import React from 'react';
import FormField from '@/components/forms/FormField';

export default function Textarea({ name, label, defaultValue = '', rows = 5, error }) {
    return (
        <FormField label={label} error={error}>
            <span className={`field-shell field-shell-textarea${error ? ' has-error' : ''}`}>
                <textarea name={name} defaultValue={defaultValue} rows={rows} />
            </span>
        </FormField>
    );
}
