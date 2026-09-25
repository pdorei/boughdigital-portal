import React from 'react';

export default function Tabs({ tabs, activeTab, onChange, label = 'Sections' }) {
    return (
        <div className="tabs" role="tablist" aria-label={label}>
            {tabs.map((tab) => (
                <button
                    key={tab}
                    className={`tab${activeTab === tab ? ' is-active' : ''}`}
                    type="button"
                    role="tab"
                    aria-selected={activeTab === tab}
                    onClick={() => onChange(tab)}
                >
                    {tab}
                </button>
            ))}
        </div>
    );
}
