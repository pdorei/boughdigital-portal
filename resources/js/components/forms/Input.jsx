import React from 'react';
import { User } from 'lucide-react';
import FormField from '@/components/forms/FormField';

export default function Input({ icon: Icon = User, label, error, defaultValue = '', value, onChange, ...inputProps }) {
    const valueProps = value === undefined ? { defaultValue } : { value, onChange };

    return (
        <FormField label={label} error={error}>
            <span className={`field-shell${error ? ' has-error' : ''}`}>
                <Icon size={17} aria-hidden="true" />
                <input {...inputProps} {...valueProps} />
            </span>
        </FormField>
    );
}
