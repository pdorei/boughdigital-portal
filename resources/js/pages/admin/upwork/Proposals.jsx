import React, { useState } from 'react';
import { Check, Eye, Pencil, Send, X } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import Card from '@/components/ui/Card';
import EmptyState from '@/components/ui/EmptyState';
import ErrorState from '@/components/ui/ErrorState';
import LoadingState from '@/components/ui/LoadingState';
import Pagination from '@/components/ui/Pagination';
import RowActions from '@/components/ui/RowActions';
import Table from '@/components/ui/Table';
import Tabs from '@/components/ui/Tabs';
import Toast from '@/components/ui/Toast';
import PageHeader from '@/components/layout/PageHeader';
import { approveUpworkProposal, getUpworkProposals, rejectUpworkProposal } from '@/api/upwork';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { usePaginatedList } from '@/hooks/usePaginatedList';
import { useWorkspace } from '@/hooks/useWorkspace';
import ProposalDrawer from '@/pages/admin/upwork/components/ProposalDrawer';
import { bidLabel, statusLabel } from '@/pages/admin/upwork/utils';
import { tabFromUrl, writeTabToUrl } from '@/utils/tabs';

const TABS = ['Pending', 'Approved', 'Sent', 'Rejected', 'Expired'];

export default function UpworkProposals() {
    const { options } = useWorkspace();
    const { formatDateTime } = useDateFormatter();
    const [tab, setTab] = useState(() => tabFromUrl('tab', TABS, 'Pending'));
    const [openId, setOpenId] = useState(() => Number(new URLSearchParams(window.location.search).get('proposal')) || null);
    const [message, setMessage] = useState(null);
    const [error, setError] = useState(null);
    const list = usePaginatedList(getUpworkProposals, { initialFilters: { status: tab.toLowerCase() }, perPage: 20 });

    function changeTab(next) {
        setTab(next);
        writeTabToUrl('tab', next);
        list.setFilter('status', next.toLowerCase());
    }

    function open(id) {
        setOpenId(id);
        const url = new URL(window.location.href);
        url.searchParams.set('proposal', id);
        window.history.replaceState(null, '', url);
    }

    function close() {
        setOpenId(null);
        const url = new URL(window.location.href);
        url.searchParams.delete('proposal');
        window.history.replaceState(null, '', url);
        list.reload();
    }

    async function act(action, successMessage) {
        setError(null);
        try {
            const response = await action();
            setMessage(response.message || successMessage);
            list.reload();
        } catch (apiError) {
            const firstError = Object.values(apiError.errors || {})[0]?.[0];
            setError(firstError || apiError.message);
        }
    }

    return (
        <div className="page-stack">
            <PageHeader
                eyebrow="Upwork"
                title="Proposals"
                description="Drafts written by Claude. Approve or edit them, then copy each one into Upwork and send it yourself."
            />
            <Toast message={message} />
            {error ? <Toast message={error} tone="error" /> : null}
            <Tabs tabs={TABS} activeTab={tab} onChange={changeTab} label="Proposal status" />
            {list.error ? <ErrorState title="Could not load proposals" copy={list.error} /> : null}
            {list.loading ? <LoadingState label="Loading proposals" /> : (
                list.rows.length ? (
                    <Card>
                        <Table
                            rowKey={(proposal) => proposal.id}
                            rows={list.rows}
                            columns={[
                                {
                                    key: 'job',
                                    label: 'Job',
                                    render: (proposal) => (
                                        <button type="button" className="link-button table-title-button" onClick={() => open(proposal.id)}>
                                            {proposal.job?.title}
                                        </button>
                                    ),
                                },
                                { key: 'track', label: 'Track', render: (proposal) => statusLabel(options, 'upwork_tracks', proposal.job?.track) },
                                { key: 'bid', label: 'Bid', render: bidLabel },
                                { key: 'target_send_at', label: tab === 'Sent' ? 'Sent' : 'Send at', render: (proposal) => formatDateTime(tab === 'Sent' ? proposal.sent_at : proposal.target_send_at, '—') },
                                { key: 'expires_at', label: 'Expires', render: (proposal) => formatDateTime(proposal.expires_at, '—') },
                                {
                                    key: 'status',
                                    label: 'Status',
                                    render: (proposal) => (
                                        <span className="inline-actions">
                                            <Badge status={proposal.status}>{statusLabel(options, 'upwork_proposal_statuses', proposal.status)}</Badge>
                                            {proposal.quality_flags?.length ? <Badge status="high">{`${proposal.quality_flags.length} to check`}</Badge> : null}
                                        </span>
                                    ),
                                },
                                {
                                    key: 'actions',
                                    label: 'Actions',
                                    render: (proposal) => (
                                        <RowActions
                                            label={`Actions for ${proposal.job?.title}`}
                                            actions={[
                                                { label: 'View', icon: Eye, onClick: () => open(proposal.id) },
                                                proposal.can.edit ? { label: 'Edit', icon: Pencil, onClick: () => open(proposal.id) } : null,
                                                proposal.can.approve ? { label: 'Approve', icon: Check, onClick: () => act(() => approveUpworkProposal(proposal.id), 'Proposal approved.') } : null,
                                                proposal.can.send ? { label: 'Copy and send', icon: Send, onClick: () => open(proposal.id) } : null,
                                                proposal.can.reject ? {
                                                    label: 'Reject',
                                                    icon: X,
                                                    variant: 'danger',
                                                    onClick: () => act(() => rejectUpworkProposal(proposal.id), 'Proposal rejected.'),
                                                    confirm: {
                                                        title: 'Reject this proposal?',
                                                        description: 'The proposal and job are kept for tuning the rules; they just stop showing as open.',
                                                        confirmLabel: 'Reject proposal',
                                                    },
                                                } : null,
                                            ]}
                                        />
                                    ),
                                },
                            ]}
                        />
                        <Pagination meta={list.meta} onPageChange={list.setPage} />
                    </Card>
                ) : <EmptyState icon={Send} title={`No ${tab.toLowerCase()} proposals`} copy="Proposals appear here once Claude drafts them for accepted jobs." />
            )}
            {openId ? (
                <ProposalDrawer
                    key={openId}
                    proposalId={openId}
                    onClose={close}
                    onChanged={(_, changedMessage) => setMessage(changedMessage)}
                />
            ) : null}
        </div>
    );
}
