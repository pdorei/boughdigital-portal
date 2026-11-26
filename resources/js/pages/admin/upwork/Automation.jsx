import React from 'react';
import PageHeader from '@/components/layout/PageHeader';
import UpworkAutomationForm from '@/pages/admin/upwork/components/UpworkAutomationForm';

export default function UpworkAutomation() {
    return (
        <div className="page-stack">
            <PageHeader
                eyebrow="Upwork"
                title="Automation"
                description="Searches, fit rules, bidding and Connects, and the prompts Claude uses. The Upwork and Claude accounts are connected in Connections."
            />
            <UpworkAutomationForm />
        </div>
    );
}
