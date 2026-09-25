import React from 'react';

export default function Toast({ message, tone = 'success' }) {
    if (!message) {
        return null;
    }

    return (
        <div className={`status-message${tone === 'error' ? ' status-message-error' : ''}`} role={tone === 'error' ? 'alert' : 'status'}>
            {message}
        </div>
    );
}
