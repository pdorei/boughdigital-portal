import React, { useEffect, useId, useRef } from 'react';
import { X } from 'lucide-react';

/**
 * A side panel for record details. Escape and the backdrop close it (unless
 * busy), focus moves into the panel and returns to the opener on close.
 */
export default function Drawer({ title, subtitle = null, onClose, busy = false, children, footer = null }) {
    const titleId = useId();
    const panelRef = useRef(null);
    const onCloseRef = useRef(onClose);
    onCloseRef.current = busy ? null : onClose;

    useEffect(() => {
        const previouslyFocused = document.activeElement;
        panelRef.current?.focus();
        document.body.classList.add('drawer-open');

        function handleKeyDown(event) {
            if (event.key === 'Escape' && !document.querySelector('.confirm-backdrop')) {
                event.stopPropagation();
                onCloseRef.current?.();
            }
        }

        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('keydown', handleKeyDown);
            document.body.classList.remove('drawer-open');
            previouslyFocused?.focus?.();
        };
    }, []);

    return (
        <div className="drawer-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onCloseRef.current?.()}>
            <aside ref={panelRef} className="drawer-panel" role="dialog" aria-modal="true" aria-labelledby={titleId} tabIndex={-1}>
                <header className="drawer-head">
                    <div className="drawer-title-block">
                        <h2 id={titleId}>{title}</h2>
                        {subtitle ? <div className="drawer-subtitle">{subtitle}</div> : null}
                    </div>
                    <button className="icon-btn" type="button" aria-label="Close" onClick={() => onCloseRef.current?.()} disabled={busy}>
                        <X size={18} />
                    </button>
                </header>
                <div className="drawer-body">{children}</div>
                {footer ? <footer className="drawer-foot">{footer}</footer> : null}
            </aside>
        </div>
    );
}
