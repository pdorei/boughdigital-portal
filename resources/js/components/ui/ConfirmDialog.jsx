import React from 'react';
import Button from '@/components/ui/Button';
import Modal from '@/components/ui/Modal';

export default function ConfirmDialog({
    cancelLabel = 'Cancel',
    confirmLabel = 'Confirm',
    description,
    loading = false,
    onCancel,
    onConfirm,
    title,
    variant = 'danger',
}) {
    return (
        <Modal
            title={title}
            description={description}
            onClose={onCancel}
            busy={loading}
            role="alertdialog"
            footer={(
                <>
                    <Button variant="secondary" onClick={onCancel} disabled={loading}>{cancelLabel}</Button>
                    <Button variant={variant} onClick={onConfirm} disabled={loading}>{loading ? 'Working...' : confirmLabel}</Button>
                </>
            )}
        />
    );
}
