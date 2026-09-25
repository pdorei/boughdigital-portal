import React from 'react';
import FormError from '@/components/forms/FormError';

export default function FormField({ label, error, children, className = '' }) {
    return (
        <label className={`field-label${className ? ` ${className}` : ''}`}>
            <span>{label}</span>
            {children}
            <FormError message={error} />
        </label>
    );
}
