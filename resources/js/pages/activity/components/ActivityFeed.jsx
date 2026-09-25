import React from 'react';
import { Activity } from 'lucide-react';
import EmptyState from '@/components/ui/EmptyState';
import ErrorState from '@/components/ui/ErrorState';
import LoadingState from '@/components/ui/LoadingState';
import Pagination from '@/components/ui/Pagination';
import { getActivity } from '@/api/activity';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { usePaginatedList } from '@/hooks/usePaginatedList';
import { useWorkspace } from '@/hooks/useWorkspace';

export default function ActivityFeed({ base, clientId = null, perPage = 20, paginate = true, syncUrl = false }) {
    const { workspaceSlug } = useWorkspace();
    const { formatDateTime } = useDateFormatter();
    const list = usePaginatedList((params) => getActivity(workspaceSlug, clientId ? { ...params, client_id: clientId } : params), { perPage, syncUrl });

    if (list.error) return <ErrorState title="Could not load activity" copy={list.error} />;
    if (list.loading) return <LoadingState label="Loading activity" />;
    if (!list.rows.length) return <EmptyState icon={Activity} title="No activity yet" copy="Changes made in the portal, API and MCP appear here." />;

    return (
        <>
            <ul className="list">
                {list.rows.map((item) => (
                    <li className="list-row" key={item.id}>
                        <div className="flex items-center gap-3">
                            <span className="activity-dot" aria-hidden="true" />
                            <strong>{item.label}{item.subject ? `: ${item.subject}` : ''}</strong>
                        </div>
                        <span className="muted">
                            {item.actor || 'System'}
                            {item.client && !clientId ? <> · <a className="link" href={`${base}/clients/${item.client.slug}`}>{item.client.name}</a></> : null}
                            {item.source !== 'portal' ? ` · via ${item.source.toUpperCase()}` : ''}
                            {' · '}
                            {formatDateTime(item.created_at)}
                        </span>
                    </li>
                ))}
            </ul>
            {paginate ? <Pagination meta={list.meta} onPageChange={list.setPage} /> : null}
        </>
    );
}
