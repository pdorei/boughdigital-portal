import React, { useState } from 'react';

export function BrandMark({ src = null }) {
    const [failed, setFailed] = useState(false);
    const imageSrc = failed ? null : src;

    return (
        <span className="brand-mark" aria-hidden="true">
            {imageSrc ? <img src={imageSrc} alt="" onError={() => setFailed(true)} /> : 'B'}
        </span>
    );
}

export function BrandLockup({ workspace, section }) {
    return (
        <div className="brand-lockup">
            <BrandMark src={workspace?.logo_url} />
            <div className="min-w-0">
                <div className="brand-name">{section === 'admin' ? 'Bough Digital' : workspace?.name || 'Bough Digital'}</div>
                <div className="brand-subtitle">{section === 'admin' ? 'Platform admin' : 'Workspace portal'}</div>
            </div>
        </div>
    );
}
