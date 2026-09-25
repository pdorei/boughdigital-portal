import React from 'react';
import { ADMIN_NAV_ITEMS } from '@/constants/routes';
import AppLayout from '@/layouts/AppLayout';

export default function AdminLayout({ title, children }) {
    return (
        <AppLayout nav={ADMIN_NAV_ITEMS} base="/admin" workspace={null} section="admin" title={title}>
            {children}
        </AppLayout>
    );
}
