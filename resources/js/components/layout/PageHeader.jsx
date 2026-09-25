import React from 'react';

export default function PageHeader({ eyebrow, title, description, action = null }) {
    return (
        <div className="page-header">
            <div>
                <div className="eyebrow">{eyebrow}</div>
                <h1>{title}</h1>
                {description ? <p>{description}</p> : null}
            </div>
            {action ? <div>{action}</div> : null}
        </div>
    );
}
