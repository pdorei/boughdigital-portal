import React from 'react';
import { BrandMark } from '@/components/layout/Brand';

function InstallBrand() {
    return (
        <div className="brand-lockup">
            <BrandMark />
            <div className="min-w-0">
                <div className="brand-name">Bough Digital</div>
                <div className="brand-subtitle">Portal installer</div>
            </div>
        </div>
    );
}

/**
 * Same guest shell as the sign-in pages, without AuthLayout's dependency on the
 * auth context (there is no session before install).
 */
export default function InstallLayout({ children, footer }) {
    return (
        <main className="guest-root">
            <div className="guest-grid">
                <section className="guest-panel">
                    <div className="guest-panel-inner">
                        <InstallBrand />
                        <div className="guest-copy">
                            <div className="guest-kicker">First-run setup</div>
                            <h1>Bough Digital Portal</h1>
                            <p>Connect the PostgreSQL database, name the portal and create the platform Super Admin. It takes about two minutes.</p>
                        </div>
                        <div className="guest-tags">
                            <span>PostgreSQL</span>
                            <span>Super Admin</span>
                            <span>Workspaces</span>
                        </div>
                    </div>
                </section>
                <section className="guest-form-wrap">
                    <div className="auth-card install-card">
                        <div className="mobile-brand">
                            <InstallBrand />
                        </div>
                        {children}
                        {footer ? <p className="install-footer">{footer}</p> : null}
                    </div>
                </section>
            </div>
        </main>
    );
}
