import React from 'react';
import PageHeader from '@/components/layout/PageHeader';
import UserForm from '@/pages/team/components/UserForm';
import { useWorkspace } from '@/hooks/useWorkspace';

export default function AdminUserEdit() {
    const { pageData } = useWorkspace();
    const user = pageData.selected_user;

    return (
        <div className="page-stack">
            <PageHeader eyebrow="Super Admin" title={`Edit ${user?.name || 'User'}`} description="Update role, workspace, status, client access, and profile details." />
            <UserForm user={user} admin base="/admin/users" />
        </div>
    );
}
