import React from 'react';

// Read-only counterpart to Input/Textarea: same label and control geometry, but the value is plain content.
export default function DisplayField({ label, icon: Icon = null, multiline = false, children }) {
    return (
        <div className="field-label">
            <span>{label}</span>
            <div className={`field-shell field-shell-readonly${multiline ? ' field-shell-textarea' : ''}`}>
                {Icon ? <Icon size={17} aria-hidden="true" /> : null}
                <div className="field-value">{children}</div>
            </div>
        </div>
    );
}
