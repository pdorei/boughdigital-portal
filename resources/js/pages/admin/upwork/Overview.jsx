import React, { useCallback, useEffect, useState } from 'react';
import { Play, RefreshCw } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import Card from '@/components/ui/Card';
import EmptyState from '@/components/ui/EmptyState';
import ErrorState from '@/components/ui/ErrorState';
import LoadingState from '@/components/ui/LoadingState';
import MetricCard from '@/components/ui/MetricCard';
import Table from '@/components/ui/Table';
import Toast from '@/components/ui/Toast';
import PageHeader from '@/components/layout/PageHeader';
import { getUpworkOverview, startUpworkRun } from '@/api/upwork';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { useWorkspace } from '@/hooks/useWorkspace';
import { statusLabel } from '@/pages/admin/upwork/utils';
import { formatNumber } from '@/utils/formatNumber';

export default function UpworkOverview() {
    const { options } = useWorkspace();
    const { formatDateTime } = useDateFormatter();
    const [data, setData] = useState(null);
    const [error, setError] = useState(null);
    const [message, setMessage] = useState(null);
    const [runError, setRunError] = useState(null);
    const [starting, setStarting] = useState(false);
    const [refreshing, setRefreshing] = useState(false);

    const load = useCallback(async () => {
        setRefreshing(true);
        try {
            setData((await getUpworkOverview()).data);
            setError(null);
        } catch (apiError) {
            setError(apiError.message);
        } finally {
            setRefreshing(false);
        }
    }, []);

    useEffect(() => {
        load();
    }, [load]);

    async function runNow() {
        setStarting(true);
        setRunError(null);
        setMessage(null);
        try {
            const response = await startUpworkRun();
            setMessage(response.message);
            window.setTimeout(load, 4000);
        } catch (apiError) {
            setRunError(apiError.errors?.upwork?.[0] || apiError.message);
        } finally {
            setStarting(false);
        }
    }

    if (error && !data) return <ErrorState title="Could not load the Upwork overview" copy={error} />;
    if (!data) return <LoadingState label="Loading Upwork overview" />;

    const { stats, automation } = data;
    const warnings = [
        automation.driver === 'fake' ? { tone: 'info', text: 'Fixture mode: jobs come from local test data and no Upwork API calls are made.' } : null,
        !automation.upwork_connected ? { tone: 'warning', text: 'Upwork is not connected.', href: '/admin/connections', link: 'Open Connections' } : null,
        !automation.claude_connected ? { tone: 'warning', text: 'Claude is not connected, so new jobs go to review without a fit score.', href: '/admin/connections', link: 'Open Connections' } : null,
        automation.claude_connected && automation.claude_budget_remaining_usd !== null && automation.claude_budget_remaining_usd <= 0 ? { tone: 'warning', text: 'The monthly Claude cap has been reached. Fit checks and drafts are paused.' } : null,
        automation.connects_blocked ? { tone: 'warning', text: `${automation.connects_blocked} New proposals are not being drafted.` } : null,
        !automation.enabled ? { tone: 'info', text: 'Automatic runs are off.', href: '/admin/upwork/automation', link: 'Open Automation' } : null,
    ].filter(Boolean);

    return (
        <div className="page-stack">
            <PageHeader
                eyebrow="Upwork"
                title="Overview"
                description={automation.enabled
                    ? `Runs every ${automation.interval_minutes} minutes.${automation.next_run_at ? ` Next run after ${formatDateTime(automation.next_run_at)}.` : ''}`
                    : 'Automatic runs are off.'}
                action={(
                    <div className="inline-actions">
                        <Button variant="secondary" icon={RefreshCw} onClick={load} disabled={refreshing}>{refreshing ? 'Refreshing...' : 'Refresh'}</Button>
                        <Button icon={Play} onClick={runNow} disabled={starting || !automation.upwork_connected}>{starting ? 'Queuing...' : 'Run now'}</Button>
                    </div>
                )}
            />
            <Toast message={message} />
            {runError ? <Toast message={runError} tone="error" /> : null}
            {warnings.map((warning) => (
                <div key={warning.text} className={`notice notice-${warning.tone}`}>
                    {warning.text} {warning.href ? <a className="strong-link" href={warning.href}>{warning.link}</a> : null}
                </div>
            ))}

            <div className="grid-stats">
                <MetricCard label="Jobs found today" value={formatNumber(stats.jobs_found_today)} note={`${stats.runs_today} runs, ${stats.api_calls_today} API calls`} />
                <MetricCard label="Awaiting review" value={formatNumber(stats.awaiting_review)} note="Yes / No decisions" />
                <MetricCard label="Open proposals" value={formatNumber(stats.open_proposals)} note="Pending or approved" />
                <MetricCard label="Sent today" value={formatNumber(stats.sent_today)} note={`${stats.connects_used_today} Connects recorded`} />
                <MetricCard label="Errors today" value={formatNumber(stats.errors_today)} note="Failed or rate-limited runs" />
            </div>

            <Card title="Recent runs">
                {data.recent_runs.length ? (
                    <Table
                        rowKey={(run) => run.id}
                        rows={data.recent_runs}
                        columns={[
                            { key: 'started_at', label: 'Started', render: (run) => formatDateTime(run.started_at) },
                            { key: 'status', label: 'Status', render: (run) => <Badge status={run.status}>{statusLabel(options, 'upwork_run_statuses', run.status)}</Badge> },
                            { key: 'trigger', label: 'Trigger', render: (run) => (run.trigger === 'manual' ? 'Manual' : 'Schedule') },
                            {
                                key: 'api_calls',
                                label: 'API calls',
                                render: (run) => <span title={`${run.search_calls} search, ${run.detail_calls} details`}>{run.api_calls} <span className="muted text-sm">({run.search_calls} + {run.detail_calls})</span></span>,
                            },
                            {
                                key: 'search_counts',
                                label: 'A / B / C',
                                render: (run) => (run.search_counts ? `${run.search_counts.a ?? 0} / ${run.search_counts.b ?? 0} / ${run.search_counts.c ?? 0}` : '—'),
                            },
                            { key: 'jobs_found', label: 'Found' },
                            { key: 'jobs_new', label: 'New' },
                            { key: 'claude_tokens_used', label: 'Claude tokens', render: (run) => formatNumber(run.claude_tokens_used) },
                            {
                                key: 'errors',
                                label: 'Errors',
                                cellClassName: 'run-errors-cell',
                                render: (run) => (run.errors.length ? <span title={run.errors.map((item) => item.message).join('\n')}>{run.errors[0].message}{run.errors.length > 1 ? ` (+${run.errors.length - 1})` : ''}</span> : <span className="muted">None</span>),
                            },
                        ]}
                    />
                ) : <EmptyState title="No runs yet" copy="Runs appear here once automation is on or you start one manually." />}
            </Card>
        </div>
    );
}
