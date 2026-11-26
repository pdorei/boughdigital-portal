import React, { useState } from 'react';
import { Eye, EyeOff, Lock } from 'lucide-react';
import FormField from '@/components/forms/FormField';

export default function PasswordInput({ name = 'password', label = 'Password', autoComplete = 'current-password', placeholder, required = false, error, value, onChange }) {
    const [visible, setVisible] = useState(false);
    const valueProps = value === undefined ? {} : { value, onChange };

    return (
        <FormField label={label} error={error}>
            <span className={`field-shell${error ? ' has-error' : ''}`}>
                <Lock size={17} />
                <input name={name} type={visible ? 'text' : 'password'} autoComplete={autoComplete} placeholder={placeholder} required={required} {...valueProps} />
                <button className="icon-btn" type="button" onClick={() => setVisible(!visible)} title={visible ? 'Hide password' : 'Show password'} aria-label={visible ? 'Hide password' : 'Show password'}>
                    {visible ? <EyeOff size={17} /> : <Eye size={17} />}
                </button>
            </span>
        </FormField>
    );
}
