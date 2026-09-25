import React from 'react';

export default function Checkbox({ name, value = '1', defaultChecked = false, disabled = false, children }) {
    return (
        <label className="checkbox-row">
            <input type="checkbox" name={name} value={value} defaultChecked={defaultChecked} disabled={disabled} />
            {children}
        </label>
    );
}
