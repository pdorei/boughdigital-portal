import React from 'react';

export default function LoadingState({ label = 'Loading' }) {
    return (
        <div className="empty">
            <div>
                <div className="loading-spinner" aria-hidden="true" />
                <h3 className="font-bold">{label}</h3>
            </div>
        </div>
    );
}
