import React from 'react';

/**
 * A pillar name with its colour swatch, used in pillar, calendar and article lists.
 */
export default function PillarName({ pillar, strong = false }) {
    if (!pillar) return <span className="table-muted">—</span>;

    return (
        <span className="pillar-name">
            <span className="pillar-swatch" style={{ background: pillar.color || 'var(--brand-border-strong)' }} aria-hidden="true" />
            {strong ? <strong>{pillar.name}</strong> : pillar.name}
        </span>
    );
}
