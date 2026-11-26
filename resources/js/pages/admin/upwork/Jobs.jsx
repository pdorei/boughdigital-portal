import React, { useState } from 'react';
import { ExternalLink, Eye, FileText, ListChecks, Send } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import Card from '@/components/ui/Card';
import EmptyState from '@/components/ui/EmptyState';
import ErrorState from '@/components/ui/ErrorState';
import LoadingState from '@/components/ui/LoadingState';
import Pagination from '@/components/ui/Pagination';
import RowActions from '@/components/ui/RowActions';
import Table from '@/components/ui/Table';
import Toast from '@/components/ui/Toast';
import FilterBar from '@/components/forms/FilterBar';
import FormField from '@/components/forms/FormField';
import SearchInput from '@/components/forms/SearchInput';
import Select from '@/components/forms/Select';
import PageHeader from '@/components/layout/PageHeader';
import { draftUpworkProposal, getUpworkJobs, sendUpworkJobToReview } from '@/api/upwork';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { usePaginatedList } from '@/hooks/usePaginatedList';
import { useWorkspace } from '@/hooks/useWorkspace';
import JobDrawer from '@/pages/admin/upwork/components/JobDrawer';
import { budgetLabel, statusLabel } from '@/pages/admin/upwork/utils';

const REVIEWABLE = ['new', 'skipped', 'rejected', 'expired', 'accepted'];

function withAll(options = [], label) {
    return [{ value: '', label }, ...options];
}

export default function UpworkJobs() {
    const { options } = useWorkspace();
    const { formatDateTime } = useDateFormatter();
    const [openId, setOpenId] = useState(null);
    const [message, setMessage] = useState(null);
    const [error, setError] = useState(null);
    const list = usePaginatedList(getUpworkJobs, {
        initialFilters: { search: '', status: '', track: '', job_type: '', date_from: '', date_to: '' },
        syncUrl: true,
    });

    async function act(action) {
        setError(null);
        try {
            const response = await action();
            setMessage(response.message);
            list.reload();
        } catch (apiError) {
            setError(Object.values(apiError.errors || {})[0]?.[0] || apiError.message);
        }
    }

    return (
        <div className="page-stack">
            <PageHeader eyebrow="Upwork" title="Jobs" description="Every job found, with the decision behind it. Nothing is deleted, so the history can be used to tune the rules." />
            <Toast message={message} />
            {error ? <Toast message={error} tone="error" /> : null}
            <FilterBar>
                <SearchInput value={list.filters.search} onChange={(value) => list.setFilter('search', value)} placeholder="Search title or keyword" />
                <Select label="Status" value={list.filters.status} options={withAll(options.upwork_job_statuses, 'All statuses')} onChange={(event) => list.setFilter('status', event.target.value)} />
                <Select label="Track" value={list.filters.track} options={withAll(options.upwork_tracks, 'All tracks')} onChange={(event) => list.setFilter('track', event.target.value)} />
                <Select label="Type" value={list.filters.job_type} options={withAll(options.upwork_job_types, 'All types')} onChange={(event) => list.setFilter('job_type', event.target.value)} />
                <FormField label="Found from">
                    <span className="field-shell"><input type="date" value={list.filters.date_from} onChange={(event) => list.setFilter('date_from', event.target.value)} /></span>
                </FormField>
                <FormField label="Found to">
                    <span className="field-shell"><input type="date" value={list.filters.date_to} onChange={(event) => list.setFilter('date_to', event.target.value)} /></span>
                </FormField>
            </FilterBar>
            {list.error ? <ErrorState title="Could not load jobs" copy={list.error} /> : null}
            {list.loading ? <LoadingState label="Loading jobs" /> : (
                list.rows.length ? (
                    <Card>
                        <Table
                            rowKey={(job) => job.id}
                            rows={list.rows}
                            columns={[
                                {
                                    key: 'title',
                                    label: 'Job',
                                    render: (job) => (
                                        <button type="button" className="link-button table-title-button" onClick={() => setOpenId(job.id)}>{job.title}</button>
                                    ),
                                },
                                { key: 'track', label: 'Track', render: (job) => statusLabel(options, 'upwork_tracks', job.track) },
                                { key: 'budget', label: 'Budget', render: budgetLabel },
                                { key: 'posted_at', label: 'Posted', render: (job) => formatDateTime(job.posted_at, '—') },
                                { key: 'fit_score', label: 'Score', render: (job) => job.fit_score ?? '—' },
                                { key: 'status', label: 'Status', render: (job) => <Badge status={job.status}>{statusLabel(options, 'upwork_job_statuses', job.status)}</Badge> },
                                {
                                    key: 'actions',
                                    label: 'Actions',
                                    render: (job) => (
                                        <RowActions
                                            label={`Actions for ${job.title}`}
                                            actions={[
                                                { label: 'View', icon: Eye, onClick: () => setOpenId(job.id) },
                                                { label: 'Open in Upwork', icon: ExternalLink, href: job.url, external: true },
                                                REVIEWABLE.includes(job.status) && !job.proposal ? { label: 'Send to review', icon: Send, onClick: () => act(() => sendUpworkJobToReview(job.id)) } : null,
                                                job.status === 'accepted' && !job.proposal ? { label: 'Draft proposal', icon: FileText, onClick: () => act(() => draftUpworkProposal(job.id)) } : null,
                                            ]}
                                        />
                                    ),
                                },
                            ]}
                        />
                        <Pagination meta={list.meta} onPageChange={list.setPage} />
                    </Card>
                ) : <EmptyState icon={ListChecks} title="No jobs found" copy="Jobs appear here after the first discovery run." />
            )}
            {openId ? <JobDrawer key={openId} jobId={openId} onClose={() => setOpenId(null)} /> : null}
        </div>
    );
}
