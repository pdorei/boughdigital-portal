import React from 'react';
import { Menu } from 'lucide-react';
import Breadcrumbs from '@/components/layout/Breadcrumbs';
import UserMenu from '@/components/layout/UserMenu';
import IconButton from '@/components/ui/IconButton';

export default function Topbar({ title, workspace, section, base, onMenu }) {
    return (
        <header className="topbar">
            <div className="flex min-w-0 items-center gap-3">
                <div className="mobile-only">
                    <IconButton icon={Menu} label="Open navigation" onClick={onMenu} />
                </div>
                <div className="min-w-0">
                    <Breadcrumbs section={section} workspaceName={workspace?.name} title={title} />
                    <h1 className="top-title truncate">{title}</h1>
                </div>
            </div>
            <div className="top-actions">
                <UserMenu workspace={workspace} base={base} />
            </div>
        </header>
    );
}
