import React from 'react';

export default function MetricCard({ label, value, note }) {
    return (
        <article className="card stat-card">
            <span className="stat-label">{label}</span>
            <span className="stat-value text-2xl">{value}</span>
            <span className="stat-note">{note}</span>
        </article>
    );
}
