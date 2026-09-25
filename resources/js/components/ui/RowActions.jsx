import React, { useEffect, useId, useRef, useState } from 'react';
import { EllipsisVertical } from 'lucide-react';
import ConfirmDialog from '@/components/ui/ConfirmDialog';

const MENU_WIDTH = 210;
const MENU_MAX_HEIGHT = 280;

function actionKey(action, index) {
    return action.key || action.label || String(index);
}

export default function RowActions({ actions = [], label = 'Open actions' }) {
    const id = useId();
    const triggerRef = useRef(null);
    const menuRef = useRef(null);
    const [open, setOpen] = useState(false);
    const [position, setPosition] = useState({ top: 0, left: 0 });
    const [confirmAction, setConfirmAction] = useState(null);
    const [loading, setLoading] = useState(false);
    const visibleActions = actions.filter(Boolean);

    useEffect(() => {
        function closeOtherMenus(event) {
            if (event.detail !== id) setOpen(false);
        }

        function closeFromOutside(event) {
            if (!open) return;
            if (triggerRef.current?.contains(event.target) || menuRef.current?.contains(event.target)) return;
            setOpen(false);
        }

        function handleEscape(event) {
            if (event.key === 'Escape') {
                setOpen(false);
                setConfirmAction(null);
            }
        }

        window.addEventListener('row-actions:open', closeOtherMenus);
        document.addEventListener('mousedown', closeFromOutside);
        document.addEventListener('keydown', handleEscape);

        return () => {
            window.removeEventListener('row-actions:open', closeOtherMenus);
            document.removeEventListener('mousedown', closeFromOutside);
            document.removeEventListener('keydown', handleEscape);
        };
    }, [id, open]);

    useEffect(() => {
        if (!open || !triggerRef.current) return;

        const rect = triggerRef.current.getBoundingClientRect();
        setPosition({
            top: Math.max(12, Math.min(rect.bottom + 8, window.innerHeight - MENU_MAX_HEIGHT - 12)),
            left: Math.max(12, Math.min(rect.right - MENU_WIDTH, window.innerWidth - MENU_WIDTH - 12)),
        });
    }, [open]);

    function toggle() {
        const next = !open;

        setOpen(next);
        if (next) {
            window.dispatchEvent(new CustomEvent('row-actions:open', { detail: id }));
        }
    }

    async function runAction(action) {
        if (action.confirm) {
            setConfirmAction(action);
            setOpen(false);
            return;
        }

        setOpen(false);
        await action.onClick?.();
    }

    async function confirm() {
        if (!confirmAction) return;

        setLoading(true);
        try {
            await confirmAction.onClick?.();
            setConfirmAction(null);
        } finally {
            setLoading(false);
        }
    }

    function focusMenuItem(direction) {
        const items = Array.from(menuRef.current?.querySelectorAll('[role="menuitem"]') || []);
        if (!items.length) return;

        const activeIndex = items.indexOf(document.activeElement);
        const nextIndex = activeIndex === -1
            ? 0
            : (activeIndex + direction + items.length) % items.length;

        items[nextIndex].focus();
    }

    function handleMenuKeyDown(event) {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            focusMenuItem(1);
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            focusMenuItem(-1);
        }
    }

    if (!visibleActions.length) {
        return null;
    }

    const normalActions = visibleActions.filter((action) => action.variant !== 'danger');
    const dangerActions = visibleActions.filter((action) => action.variant === 'danger');
    const orderedActions = [...normalActions, ...dangerActions];

    return (
        <>
            <div className="row-actions">
                <button
                    ref={triggerRef}
                    type="button"
                    className="row-actions-trigger"
                    aria-label={label}
                    aria-expanded={open}
                    aria-haspopup="menu"
                    title="Actions"
                    onClick={toggle}
                    onKeyDown={(event) => {
                        if (event.key === 'ArrowDown') {
                            event.preventDefault();
                            setOpen(true);
                            requestAnimationFrame(() => focusMenuItem(1));
                        }
                    }}
                >
                    <EllipsisVertical size={18} />
                </button>
                {open ? (
                    <div
                        ref={menuRef}
                        className="row-actions-menu"
                        role="menu"
                        style={{ top: position.top, left: position.left }}
                        onKeyDown={handleMenuKeyDown}
                    >
                        {orderedActions.map((action, index) => {
                            const Icon = action.icon;
                            const separated = index > 0 && action.variant === 'danger' && orderedActions[index - 1]?.variant !== 'danger';
                            const className = `row-actions-item${action.variant === 'danger' ? ' row-actions-item-danger' : ''}${separated ? ' row-actions-item-separated' : ''}`;

                            if (action.href) {
                                return (
                                    <a
                                        key={actionKey(action, index)}
                                        className={className}
                                        href={action.href}
                                        role="menuitem"
                                        onClick={() => setOpen(false)}
                                        {...(action.external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
                                    >
                                        {Icon ? <Icon size={16} /> : null}
                                        <span>{action.label}</span>
                                    </a>
                                );
                            }

                            return (
                                <button key={actionKey(action, index)} className={className} type="button" role="menuitem" onClick={() => runAction(action)}>
                                    {Icon ? <Icon size={16} /> : null}
                                    <span>{action.label}</span>
                                </button>
                            );
                        })}
                    </div>
                ) : null}
            </div>
            {confirmAction ? (
                <ConfirmDialog
                    title={confirmAction.confirm.title}
                    description={confirmAction.confirm.description}
                    confirmLabel={confirmAction.confirm.confirmLabel || confirmAction.label}
                    cancelLabel={confirmAction.confirm.cancelLabel}
                    variant={confirmAction.variant === 'danger' ? 'danger' : 'primary'}
                    loading={loading}
                    onCancel={() => setConfirmAction(null)}
                    onConfirm={confirm}
                />
            ) : null}
        </>
    );
}
