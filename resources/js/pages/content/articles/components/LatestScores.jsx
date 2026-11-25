import React from 'react';

/**
 * The most recent AI and human scores side by side.
 */
export default function LatestScores({ ai, human, compact = false }) {
    if (compact) {
        if (!ai && !human) return <span className="table-muted">Not scored</span>;

        return (
            <span className="score-pair">
                {ai ? <span>AI <strong>{ai.overall_score}</strong></span> : null}
                {human ? <span>Human <strong>{human.overall_score}</strong></span> : null}
            </span>
        );
    }

    return (
        <div className="score-summary">
            {[['AI', ai], ['Human', human]].map(([label, score]) => (
                <div className="score-tile" key={label}>
                    <span>{label}</span>
                    <strong>{score ? score.overall_score : '—'}</strong>
                </div>
            ))}
        </div>
    );
}
