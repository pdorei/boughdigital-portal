import React from 'react';
import FormField from '@/components/forms/FormField';

export default function Select({ name, label, options = [], defaultValue = '', error, disabled = false, value, onChange, className = '' }) {
    const controlProps = value === undefined ? { defaultValue } : { value, onChange };

    return (
        <FormField label={label} error={error} className={className}>
            <span className={`field-shell${error ? ' has-error' : ''}`}>
                <select name={name} disabled={disabled} {...controlProps}>
                    {options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                </select>
            </span>
        </FormField>
    );
}
