import React, { useEffect, useState } from 'react';
import Badge from '@/components/ui/Badge';
import Drawer from '@/components/ui/Drawer';
import ErrorState from '@/components/ui/ErrorState';
import LoadingState from '@/components/ui/LoadingState';
import { getUpworkJob } from '@/api/upwork';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { useWorkspace } from '@/hooks/useWorkspace';
import JobFacts from '@/pages/admin/upwork/components/JobFacts';
import { statusLabel } from '@/pages/admin/upwork/utils';

const STAGES = { rules: 'Hard rules', fit: 'Claude fit check', rate: 'Rate rules', human: 'Person', system: 'System' };

export default function JobDrawer({ jobId, onClose }) {
    const { options } = useWorkspace();
    const { formatDateTime } = useDateFormatter();
    const [job, setJob] = useState(null);
    const [error, setError] = useState(null);

    useEffect(() => {
        getUpworkJob(jobId).then((response) => setJob(response.data)).catch((apiError) => setError(apiError.message));
    }, [jobId]);

    const decision = job?.latest_decision;

    return (
        <Drawer
            title={job?.title || 'Job'}
            subtitle={job ? <Badge status={job.status}>{statusLabel(options, 'upwork_job_statuses', job.status)}</Badge> : null}
            onClose={onClose}
        >
            {error ? <ErrorState title="Could not load the job" copy={error} /> : null}
            {!job && !error ? <LoadingState label="Loading job" /> : null}
            {job ? (
                <div className="drawer-sections">
                    <JobFacts job={job} />
                    {decision ? (
                        <section className="drawer-section">
                            <h3>Decision</h3>
                            <p><strong>{STAGES[decision.stage] || decision.stage}:</strong> {decision.reason || 'No reason recorded.'}</p>
                            {decision.red_flags?.length ? (
                                <ul className="flag-list">{decision.red_flags.map((flag) => <li key={flag}>{flag}</li>)}</ul>
                            ) : null}
                        </section>
                    ) : null}
                    <section className="drawer-section">
                        <h3>Description</h3>
                        <p className="preserve-lines">{job.description || 'No description.'}</p>
                    </section>
                    <section className="drawer-section">
                        <h3>Details</h3>
                        <dl className="job-facts">
                            <div><dt>Type</dt><dd>{statusLabel(options, 'upwork_job_types', job.job_type)}</dd></div>
                            <div><dt>Experience</dt><dd>{job.experience_level ? statusLabel({}, '', job.experience_level) : 'Not stated'}</dd></div>
                            <div><dt>Duration</dt><dd>{job.duration ? statusLabel({}, '', job.duration) : 'Not stated'}</dd></div>
                            <div><dt>Found by</dt><dd>{job.search_key ? `Search ${job.search_key.toUpperCase()}` : 'Unknown'}{job.keyword ? `, "${job.keyword}"` : ''}</dd></div>
                            <div><dt>First seen</dt><dd>{formatDateTime(job.first_seen_at)}</dd></div>
                            <div><dt>Upwork ID</dt><dd className="mono">{job.upwork_job_id}</dd></div>
                            <div><dt>Client reviews</dt><dd>{job.client?.reviews_count ?? 'Unknown'}</dd></div>
                        </dl>
                        {job.skills?.length ? <div className="tag-list">{job.skills.map((skill) => <span key={skill} className="tag-chip tag-chip-static">{skill}</span>)}</div> : null}
                    </section>
                    {job.screening_questions?.length ? (
                        <section className="drawer-section">
                            <h3>Screening questions</h3>
                            <ol className="plain-list">{job.screening_questions.map((question) => <li key={question}>{question}</li>)}</ol>
                        </section>
                    ) : null}
                    <section className="drawer-section">
                        <h3>Decision history</h3>
                        <ol className="timeline">
                            {(job.decisions || []).map((item) => (
                                <li key={item.id}>
                                    <div className="between-row">
                                        <strong>{STAGES[item.stage] || item.stage}</strong>
                                        <span className="muted text-sm">{formatDateTime(item.decided_at)}</span>
                                    </div>
                                    <div className="inline-actions">
                                        <Badge status={item.outcome === 'accept' ? 'accepted' : item.outcome}>{item.outcome}</Badge>
                                        {item.fit_score !== null ? <span className="muted text-sm">Score {item.fit_score}</span> : null}
                                        {item.decided_by ? <span className="muted text-sm">by {item.decided_by.name}</span> : null}
                                    </div>
                                    {item.reason ? <p>{item.reason}</p> : null}
                                    {item.sector || item.deliverables?.length ? (
                                        <p className="muted text-sm">
                                            {[item.sector, item.deliverables?.join(', '), item.ongoing ? 'Likely ongoing' : null].filter(Boolean).join(' · ')}
                                        </p>
                                    ) : null}
                                    {item.red_flags?.length ? <ul className="flag-list">{item.red_flags.map((flag) => <li key={flag}>{flag}</li>)}</ul> : null}
                                </li>
                            ))}
                            {!job.decisions?.length ? <li className="muted">No decisions yet.</li> : null}
                        </ol>
                    </section>
                    {job.proposal ? (
                        <a className="btn btn-secondary" href={`/admin/upwork/proposals?tab=${job.proposal.status}&proposal=${job.proposal.id}`}>Open proposal</a>
                    ) : null}
                </div>
            ) : null}
        </Drawer>
    );
}
