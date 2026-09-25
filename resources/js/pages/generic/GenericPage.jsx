import React from 'react';
import { Hourglass } from 'lucide-react';
import Card from '@/components/ui/Card';
import EmptyState from '@/components/ui/EmptyState';
import PageHeader from '@/components/layout/PageHeader';
import { useWorkspace } from '@/hooks/useWorkspace';

export default function GenericPage({ title }) {
    const { workspaceName } = useWorkspace();

    return (
        <div className="page-stack">
            <PageHeader eyebrow={workspaceName || 'Bough Digital'} title={title} />
            <Card>
                <EmptyState icon={Hourglass} title={`${title} is not available yet`} copy="This module is planned for a later delivery phase." />
            </Card>
        </div>
    );
}
