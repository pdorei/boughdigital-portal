import React from 'react';
import Card from '@/components/ui/Card';
import PageHeader from '@/components/layout/PageHeader';
import { useWorkspace } from '@/hooks/useWorkspace';
import DocumentManager from '@/pages/documents/components/DocumentManager';

export default function DocumentsIndex({ base }) {
    const { pageData, workspaceName } = useWorkspace();

    return (
        <div className="page-stack">
            <PageHeader eyebrow={workspaceName} title="Documents" description="Uploaded client files and linked external documents. Uploaded files are private to people with access to the client." />
            <Card padded>
                <DocumentManager base={base} clients={pageData.form_options?.clients || []} syncUrl />
            </Card>
        </div>
    );
}
