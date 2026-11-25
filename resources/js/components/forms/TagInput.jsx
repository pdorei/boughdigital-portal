import React, { useId, useState } from 'react';
import { X } from 'lucide-react';
import FormError from '@/components/forms/FormError';

function addUnique(values, additions) {
    const seen = new Set(values.map((value) => value.toLowerCase()));

    return additions.reduce((next, addition) => {
        const value = addition.trim();
        if (!value || seen.has(value.toLowerCase())) return next;

        seen.add(value.toLowerCase());

        return [...next, value];
    }, values);
}

/**
 * A list of short values shown as chips. Enter or a comma adds the typed
 * value, clicking a chip moves it back into the input for editing, and the
 * cross removes it.
 */
export default function TagInput({ label, values = [], onChange, placeholder = 'Type and press Enter', max = null, hint = null, error }) {
    const id = useId();
    const [draft, setDraft] = useState('');
    const full = max !== null && values.length >= max;

    function commit(text = draft) {
        const next = addUnique(values, text.split(/[,\n]/));
        const allowed = max !== null ? next.slice(0, max) : next;

        if (allowed.length !== values.length) onChange(allowed);
        setDraft('');
    }

    function handleKeyDown(event) {
        if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            commit();
        }
    }

    function edit(index) {
        const remaining = values.filter((_, position) => position !== index);
        const withDraft = draft.trim() ? addUnique(remaining, [draft]) : remaining;

        onChange(withDraft);
        setDraft(values[index]);
        document.getElementById(id)?.focus();
    }

    return (
        <div className="field-label">
            <label htmlFor={id}>{label}</label>
            <div className={`field-shell tag-input${error ? ' has-error' : ''}`}>
                {values.map((value, index) => (
                    <span className="tag-chip" key={value}>
                        <button type="button" className="tag-chip-text" onClick={() => edit(index)} title="Edit">{value}</button>
                        <button type="button" className="tag-chip-remove" onClick={() => onChange(values.filter((_, position) => position !== index))} aria-label={`Remove ${value}`}>
                            <X size={13} />
                        </button>
                    </span>
                ))}
                <input
                    id={id}
                    value={draft}
                    onChange={(event) => setDraft(event.target.value)}
                    onKeyDown={handleKeyDown}
                    onBlur={() => commit()}
                    placeholder={full ? `Maximum of ${max} reached` : placeholder}
                    disabled={full}
                />
            </div>
            {hint ? <span className="field-hint">{hint}</span> : null}
            <FormError message={error} />
        </div>
    );
}
