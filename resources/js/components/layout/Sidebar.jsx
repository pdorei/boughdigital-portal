import React from 'react';
import { X } from 'lucide-react';
import IconButton from '@/components/ui/IconButton';
import { BrandLockup } from '@/components/layout/Brand';
import { usePermissions } from '@/hooks/usePermissions';
import { useWorkspace } from '@/hooks/useWorkspace';

export default function Sidebar({ nav, base, workspace, section, onClose }) {
    const { page, navBadges } = useWorkspace();
    const { can } = usePermissions();

    return (
        <aside className="sidebar">
            <div className="sidebar-head">
                <BrandLockup workspace={workspace} section={section} />
                <IconButton icon={X} label="Close navigation" onClick={onClose} />
            </div>
            <nav className="nav-list" aria-label="Main">
                {nav.filter((item) => !item.ability || can(item.ability)).map(({ type, slug, active, label, icon: Icon, badge }) => {
                    if (type === 'section') {
                        return <div key={`section-${label}`} className="nav-section-label">{label}</div>;
                    }

                    const isActive = (active || [slug]).includes(page);
                    // Counts come from the server (bootstrap navBadges), never computed here.
                    const count = badge ? Number(navBadges?.[badge] || 0) : 0;

                    return (
                        <a key={slug} className={`nav-link${isActive ? ' is-active' : ''}`} href={`${base}/${slug}`} aria-current={isActive ? 'page' : undefined}>
                            <Icon size={18} aria-hidden="true" />
                            <span>{label}</span>
                            {count > 0 ? <span className="nav-badge" aria-label={`${count} waiting`}>{count > 99 ? '99+' : count}</span> : null}
                        </a>
                    );
                })}
            </nav>
        </aside>
    );
}
