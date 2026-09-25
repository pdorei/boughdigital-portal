import React from 'react';
import Toast from '@/components/ui/Toast';
import { BrandLockup } from '@/components/layout/Brand';
import { useAuth } from '@/hooks/useAuth';

export default function AuthLayout({ title, subtitle, children }) {
    const { status } = useAuth();

    return (
        <main className="guest-root">
            <div className="guest-grid">
                <section className="guest-panel">
                    <div className="guest-panel-inner">
                        <BrandLockup section="admin" />
                        <div className="guest-copy">
                            <div className="guest-kicker">Client delivery portal</div>
                            <h1>Bough Digital</h1>
                            <p>Workspace operations, client records, content work, SEO tasks, and MCP actions in one focused system.</p>
                        </div>
                        <div className="guest-tags">
                            <span>Workspace tenancy</span>
                            <span>REST API</span>
                            <span>MCP tools</span>
                        </div>
                    </div>
                </section>
                <section className="guest-form-wrap">
                    <div className="auth-card">
                        <div className="mobile-brand">
                            <BrandLockup section="admin" />
                        </div>
                        <div className="auth-card-panel">
                            <h1>{title}</h1>
                            <p>{subtitle}</p>
                            <Toast message={status} />
                            {children}
                        </div>
                    </div>
                </section>
            </div>
        </main>
    );
}
