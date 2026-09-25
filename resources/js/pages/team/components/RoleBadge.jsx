import React from 'react';
import Badge from '@/components/ui/Badge';
import { useWorkspace } from '@/hooks/useWorkspace';
import { optionLabel } from '@/utils/options';

export default function RoleBadge({ role }) {
    const { options } = useWorkspace();

    return <Badge status={role === 'super_admin' ? 'info' : 'ready'}>{optionLabel(options.roles, role)}</Badge>;
}
