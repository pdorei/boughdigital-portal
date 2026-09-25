import React from 'react';

export default function Card({ title, action = null, children, padded = false }) {
    return (
        <section className="card">
            {title || action ? (
                <div className="card-head">
                    {title ? <h2 className="card-title">{title}</h2> : <span />}
                    {action}
                </div>
            ) : null}
            <div className={padded ? 'card-pad' : ''}>{children}</div>
        </section>
    );
}
