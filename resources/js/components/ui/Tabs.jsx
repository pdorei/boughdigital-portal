import React, { useEffect, useRef } from 'react';

export default function Tabs({ tabs, activeTab, onChange, label = 'Sections' }) {
    const listRef = useRef(null);

    // Keep the active tab visible when the row scrolls sideways (narrow screens,
    // or a page opened on a later tab). Only the tab row scrolls, not the page.
    useEffect(() => {
        const list = listRef.current;
        const active = list?.querySelector('.is-active');

        if (active && list.scrollWidth > list.clientWidth) {
            list.scrollLeft = active.offsetLeft - (list.clientWidth - active.clientWidth) / 2;
        }
    }, [activeTab]);

    return (
        <div ref={listRef} className="tabs" role="tablist" aria-label={label}>
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
