import React, { useState } from 'react';
import Sidebar from '@/components/layout/Sidebar';
import Topbar from '@/components/layout/Topbar';
import Toast from '@/components/ui/Toast';
import { consumeFlash } from '@/utils/flash';

export default function AppLayout({ nav, base, workspace, section, title, children }) {
    const [mobileOpen, setMobileOpen] = useState(false);
    const [flashMessage] = useState(consumeFlash);

    return (
        <main className="app-root">
            <div className="app-shell">
                <div className="hidden lg:block">
                    <Sidebar nav={nav} base={base} workspace={workspace} section={section} onClose={() => setMobileOpen(false)} />
                </div>
                {mobileOpen ? (
                    <div className="mobile-sidebar">
                        <Sidebar nav={nav} base={base} workspace={workspace} section={section} onClose={() => setMobileOpen(false)} />
                        <button className="mobile-scrim" type="button" onClick={() => setMobileOpen(false)} aria-label="Close navigation" />
                    </div>
                ) : null}
                <section className="main-pane">
                    <Topbar title={title} workspace={workspace} section={section} base={base} onMenu={() => setMobileOpen(true)} />
                    <div className="content-wrap">
                        <Toast message={flashMessage} />
                        {children}
                    </div>
                </section>
            </div>
        </main>
    );
}
