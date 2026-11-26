import React from 'react';
import PageHeader from '@/components/layout/PageHeader';
import UpworkConfigurationForm from '@/pages/admin/upwork/components/UpworkConfigurationForm';

export default function UpworkConfiguration() {
    return (
        <div className="page-stack">
            <PageHeader
                eyebrow="Upwork"
                title="Configuration"
                description="Searches, fit rules, bidding and Connects, and the prompts Claude uses. The Upwork and Claude accounts are connected in Connections."
            />
            <UpworkConfigurationForm />
        </div>
    );
}
