import React from 'react';
import PageHeader from '@/components/layout/PageHeader';
import UserForm from '@/pages/team/components/UserForm';

export default function AdminUserCreate() {
    return (
        <div className="page-stack">
            <PageHeader eyebrow="Super Admin" title="Create User" description="Create a platform or workspace user with deliberate role assignment." />
            <UserForm admin base="/admin/users" />
        </div>
    );
}
