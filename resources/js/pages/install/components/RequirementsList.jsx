import React, { useId, useState } from 'react';
import { Check, ChevronDown, ShieldCheck, TriangleAlert, X } from 'lucide-react';

export default function RequirementsList({ requirements, passed }) {
    const failing = requirements.filter((check) => !check.ok).length;
    const [open, setOpen] = useState(failing > 0);
    const listId = useId();

    return (
        <div className="install-checks">
            <button type="button" className="install-checks-toggle" aria-expanded={open} aria-controls={listId} onClick={() => setOpen(!open)}>
                {passed ? (
                    <span className="install-checks-summary is-ok">
                        <ShieldCheck size={18} aria-hidden="true" /> All {requirements.length} server checks passed
                    </span>
                ) : (
                    <span className="install-checks-summary is-error">
                        <TriangleAlert size={18} aria-hidden="true" /> {failing} of {requirements.length} server checks need fixing
                    </span>
                )}
                <ChevronDown size={17} aria-hidden="true" className={open ? 'is-open' : ''} />
            </button>

            <ul id={listId} hidden={!open}>
                {requirements.map((check) => (
                    <li key={check.label}>
                        <span className="install-check-label">
                            {check.ok
                                ? <Check size={15} strokeWidth={3} className="is-ok" aria-label="Passed" />
                                : <X size={15} strokeWidth={3} className="is-error" aria-label="Failed" />}
                            {check.label}
                        </span>
                        <span className={check.ok ? 'install-check-detail' : 'install-check-detail is-error'}>{check.detail}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
