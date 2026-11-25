import React from 'react';
import { Check, TriangleAlert } from 'lucide-react';

export default function StatusNotice({ ok, message }) {
    if (!message) {
        return null;
    }

    return (
        <div className={`install-notice ${ok ? 'is-ok' : 'is-error'}`} role={ok ? 'status' : 'alert'}>
            {ok ? <Check size={18} strokeWidth={2.5} aria-hidden="true" /> : <TriangleAlert size={18} aria-hidden="true" />}
            <span>{message}</span>
        </div>
    );
}
