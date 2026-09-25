import React from 'react';
import { MoreHorizontal } from 'lucide-react';

export default function ActionGroup({ children, align = 'end', label = 'Actions' }) {
    return (
        <div className={`action-group action-group-${align}`}>
            <div className="action-group-inline">
                {children}
            </div>
            <details className="action-menu">
                <summary aria-label={label} title={label}>
                    <MoreHorizontal size={17} />
                </summary>
                <div className="action-menu-panel">
                    {children}
                </div>
            </details>
        </div>
    );
}
