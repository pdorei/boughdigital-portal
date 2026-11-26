import React from 'react';
import { ExternalLink } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { useWorkspace } from '@/hooks/useWorkspace';
import { ageLabel, budgetLabel, clientSummary, statusLabel } from '@/pages/admin/upwork/utils';

/**
 * The job facts shared by the review cards and the drawers.
 */
export default function JobFacts({ job, showStatus = false }) {
    const { options } = useWorkspace();
    const { formatDateTime } = useDateFormatter();

    return (
        <dl className="job-facts">
            <div><dt>Budget</dt><dd>{budgetLabel(job)}</dd></div>
            <div><dt>Posted</dt><dd>{formatDateTime(job.posted_at, 'Unknown')}{job.posted_at ? <span className="muted"> ({ageLabel(job.posted_at)})</span> : null}</dd></div>
            <div><dt>Proposals</dt><dd>{job.proposals_count ?? 'Unknown'}</dd></div>
            <div><dt>Client</dt><dd>{clientSummary(job.client)}</dd></div>
            <div><dt>Track</dt><dd><Badge>{statusLabel(options, 'upwork_tracks', job.track)}</Badge></dd></div>
            <div><dt>Fit score</dt><dd>{job.fit_score ?? 'Not scored'}</dd></div>
            {showStatus ? <div><dt>Status</dt><dd><Badge status={job.status}>{statusLabel(options, 'upwork_job_statuses', job.status)}</Badge></dd></div> : null}
            <div>
                <dt>Upwork</dt>
                <dd>
                    <a className="strong-link inline-link" href={job.url} target="_blank" rel="noopener noreferrer">
                        Open job <ExternalLink size={14} aria-hidden="true" />
                        <span className="sr-only"> (opens in a new tab)</span>
                    </a>
                </dd>
            </div>
        </dl>
    );
}
