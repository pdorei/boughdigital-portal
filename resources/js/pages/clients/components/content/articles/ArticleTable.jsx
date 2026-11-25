import React from 'react';
import { BarChart3, Eye, FileText, Pencil, Trash2 } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import EmptyState from '@/components/ui/EmptyState';
import RowActions from '@/components/ui/RowActions';
import Table from '@/components/ui/Table';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { useWorkspace } from '@/hooks/useWorkspace';
import { formatNumber } from '@/utils/formatNumber';
import { optionLabel } from '@/utils/options';
import PillarName from '@/pages/clients/components/content/PillarName';
import LatestScores from '@/pages/content/articles/components/LatestScores';

export default function ArticleTable({ articles, articleUrl, onDelete }) {
    const { formatDate } = useDateFormatter();
    const { options } = useWorkspace();

    if (!articles.length) {
        return <EmptyState icon={FileText} title="No articles yet" copy="Articles are the content actually written for this client: body, SEO details and scores." />;
    }

    return (
        <Table
            columns={[
                { key: 'title', label: 'Title', render: (article) => <a className="client-link" href={articleUrl(article.id)}>{article.title}</a> },
                { key: 'keyword', label: 'Primary keyword', render: (article) => article.primary_keyword || <span className="table-muted">—</span> },
                { key: 'pillar', label: 'Pillar', render: (article) => <PillarName pillar={article.pillar} /> },
                { key: 'writer', label: 'Writer', render: (article) => article.writer?.name || <span className="table-muted">Unassigned</span> },
                { key: 'status', label: 'Status', render: (article) => <Badge status={article.status}>{optionLabel(options.article_statuses, article.status)}</Badge> },
                { key: 'words', label: 'Words', render: (article) => (article.word_count ? formatNumber(article.word_count) : <span className="table-muted">—</span>) },
                { key: 'published', label: 'Published', render: (article) => formatDate(article.published_at, '—') },
                { key: 'score', label: 'Latest score', render: (article) => <LatestScores ai={article.latest_ai_score} human={article.latest_human_score} compact /> },
                {
                    key: 'actions',
                    label: 'Actions',
                    render: (article) => (
                        <RowActions
                            label={`Open actions for ${article.title}`}
                            actions={[
                                { label: 'View', icon: Eye, href: articleUrl(article.id) },
                                article.can?.update ? { label: 'Edit', icon: Pencil, href: `${articleUrl(article.id)}/edit` } : null,
                                { label: article.can?.score ? 'Score / view scores' : 'View scores', icon: BarChart3, href: `${articleUrl(article.id)}#scores` },
                                article.can?.delete ? {
                                    label: 'Delete',
                                    icon: Trash2,
                                    variant: 'danger',
                                    confirm: {
                                        title: `Delete "${article.title}"?`,
                                        description: 'The article and its score history are removed permanently. A linked calendar entry is kept without its article.',
                                        confirmLabel: 'Delete article',
                                    },
                                    onClick: () => onDelete(article),
                                } : null,
                            ]}
                        />
                    ),
                },
            ]}
            rows={articles}
            rowKey={(article) => article.id}
        />
    );
}
