import React from 'react';
import Button from '@/components/ui/Button';

export default function Pagination({ meta, onPageChange }) {
    if (!meta || meta.total === 0) {
        return null;
    }

    return (
        <nav className="pagination-row" aria-label="Pagination">
            <span className="muted text-sm">{meta.from}–{meta.to} of {meta.total}</span>
            {meta.last_page > 1 ? (
                <div className="pagination-actions">
                    <Button variant="secondary" disabled={meta.current_page <= 1} onClick={() => onPageChange(meta.current_page - 1)}>Previous</Button>
                    <Button variant="secondary" disabled={meta.current_page >= meta.last_page} onClick={() => onPageChange(meta.current_page + 1)}>Next</Button>
                </div>
            ) : null}
        </nav>
    );
}
