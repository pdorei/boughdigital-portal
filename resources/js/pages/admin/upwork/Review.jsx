import React, { useState } from 'react';
import { ClipboardCheck, ThumbsDown, ThumbsUp } from 'lucide-react';
import Button from '@/components/ui/Button';
import Card from '@/components/ui/Card';
import EmptyState from '@/components/ui/EmptyState';
import ErrorState from '@/components/ui/ErrorState';
import LoadingState from '@/components/ui/LoadingState';
import Pagination from '@/components/ui/Pagination';
import Toast from '@/components/ui/Toast';
import PageHeader from '@/components/layout/PageHeader';
import { getUpworkJobs, reviewUpworkJob } from '@/api/upwork';
import { usePaginatedList } from '@/hooks/usePaginatedList';
import JobFacts from '@/pages/admin/upwork/components/JobFacts';

export default function UpworkReview() {
    const list = usePaginatedList(getUpworkJobs, { initialFilters: { status: 'review' }, perPage: 20 });
    const [busy, setBusy] = useState(null);
    const [message, setMessage] = useState(null);
    const [error, setError] = useState(null);

    async function decide(job, decision) {
        setBusy(`${job.id}:${decision}`);
        setError(null);
        setMessage(null);
        try {
            const response = await reviewUpworkJob(job.id, decision);
            setMessage(response.message);
            list.reload();
        } catch (apiError) {
            setError(apiError.errors?.status?.[0] || apiError.message);
            list.reload();
        } finally {
            setBusy(null);
        }
    }

    return (
        <div className="page-stack">
            <PageHeader
                eyebrow="Upwork"
                title="Review"
                description="Borderline jobs, oldest first. Yes drafts a proposal for approval; No rejects the job (it stays in the history)."
            />
            <Toast message={message} />
            {error ? <Toast message={error} tone="error" /> : null}
            {list.error ? <ErrorState title="Could not load the review queue" copy={list.error} /> : null}
            {list.loading ? <LoadingState label="Loading review queue" /> : null}
            {!list.loading && !list.error && !list.rows.length ? (
                <EmptyState icon={ClipboardCheck} title="Nothing to review" copy="Jobs that need a Yes or No will appear here." />
            ) : null}
            {!list.loading ? (
                <div className="review-list">
                    {list.rows.map((job) => {
                        const decision = job.latest_decision;
                        const disabled = Boolean(busy);

                        return (
                            <Card key={job.id}>
                                <article className="card-pad review-card" aria-labelledby={`job-${job.id}`}>
                                    <div className="review-card-head">
                                        <h2 id={`job-${job.id}`} className="review-card-title">{job.title}</h2>
                                        {job.fit_score !== null ? <span className="score-pill" aria-label={`Fit score ${job.fit_score}`}>{job.fit_score}</span> : null}
                                    </div>
                                    <JobFacts job={job} />
                                    {decision?.reason ? <p className="review-reason"><strong>Why review:</strong> {decision.reason}</p> : null}
                                    {decision?.red_flags?.length ? <ul className="flag-list">{decision.red_flags.map((flag) => <li key={flag}>{flag}</li>)}</ul> : null}
                                    {job.excerpt ? <p className="muted text-sm">{job.excerpt}</p> : null}
                                    <div className="inline-actions">
                                        <Button icon={ThumbsUp} onClick={() => decide(job, 'yes')} disabled={disabled} aria-label={`Yes, write a proposal for ${job.title}`}>
                                            {busy === `${job.id}:yes` ? 'Saving...' : 'Yes'}
                                        </Button>
                                        <Button variant="secondary" icon={ThumbsDown} onClick={() => decide(job, 'no')} disabled={disabled} aria-label={`No, reject ${job.title}`}>
                                            {busy === `${job.id}:no` ? 'Saving...' : 'No'}
                                        </Button>
                                    </div>
                                </article>
                            </Card>
                        );
                    })}
                </div>
            ) : null}
            <Pagination meta={list.meta} onPageChange={list.setPage} />
        </div>
    );
}
