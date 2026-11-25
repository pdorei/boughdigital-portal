import React, { useState } from 'react';
import { Palette } from 'lucide-react';
import FormField from '@/components/forms/FormField';

export default function ColourInput({ name, label, defaultValue = '#000000', error }) {
    const [value, setValue] = useState(defaultValue || '#000000');

    function handleChange(event) {
        setValue(event.target.value);
    }

    return (
        <FormField label={label} error={error}>
            <span className={`field-shell colour-shell${error ? ' has-error' : ''}`}>
                <input className="colour-picker" type="color" aria-label={`${label} picker`} value={value} onChange={handleChange} />
                <Palette size={17} />
                <input name={name} value={value} onChange={handleChange} pattern="^#[0-9A-Fa-f]{6}$" />
            </span>
        </FormField>
    );
}
