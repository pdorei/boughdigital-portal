import React from 'react';

export default function Checkbox({ name, value = '1', defaultChecked = false, disabled = false, checked, onChange, children }) {
    const checkedProps = checked === undefined ? { defaultChecked } : { checked, onChange };

    return (
        <label className="checkbox-row">
            <input type="checkbox" name={name} value={value} disabled={disabled} {...checkedProps} />
            {children}
        </label>
    );
}
