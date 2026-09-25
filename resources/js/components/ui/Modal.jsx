import React, { useEffect, useId, useRef } from 'react';
import { X } from 'lucide-react';

export default function Modal({ title, description = null, onClose, busy = false, wide = false, role = 'dialog', children, footer = null }) {
    const titleId = useId();
    const descriptionId = useId();
    const dialogRef = useRef(null);
    const onCloseRef = useRef(onClose);
    onCloseRef.current = busy ? null : onClose;

    useEffect(() => {
        const previouslyFocused = document.activeElement;
        const firstField = dialogRef.current?.querySelector('input, select, textarea, button:not([data-modal-close])');
        (firstField || dialogRef.current)?.focus();

        function handleKeyDown(event) {
            if (event.key === 'Escape') {
                event.stopPropagation();
                onCloseRef.current?.();
            }
        }

        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('keydown', handleKeyDown);
            previouslyFocused?.focus?.();
        };
    }, []);

    return (
        <div className="confirm-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onCloseRef.current?.()}>
            <div
                ref={dialogRef}
                className={`confirm-dialog${wide ? ' modal-wide' : ''}`}
                role={role}
                aria-modal="true"
                aria-labelledby={titleId}
                aria-describedby={description ? descriptionId : undefined}
                tabIndex={-1}
            >
                <div className="between-row">
                    <h2 id={titleId}>{title}</h2>
                    <button className="icon-btn" type="button" data-modal-close aria-label="Close" onClick={() => onCloseRef.current?.()} disabled={busy}>
                        <X size={18} />
                    </button>
                </div>
                {description ? <p id={descriptionId}>{description}</p> : null}
                {children}
                {footer ? <div className="inline-actions action-group-end">{footer}</div> : null}
            </div>
        </div>
    );
}
