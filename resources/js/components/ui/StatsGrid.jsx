import React from 'react';

export default function StatsGrid({ stats }) {
    return (
        <div className="grid-stats">
            {stats.map((stat) => (
                <article className="card stat-card" key={stat.label}>
                    <span className="stat-label">{stat.label}</span>
                    <div className="stat-value">{stat.value}</div>
                    {stat.trend ? <div className="stat-note">{stat.trend}</div> : null}
                </article>
            ))}
        </div>
    );
}
