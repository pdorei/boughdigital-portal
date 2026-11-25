import React, { useCallback, useEffect, useState } from 'react';
import { BarChart3, Plus } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import Card from '@/components/ui/Card';
import EmptyState from '@/components/ui/EmptyState';
import ErrorState from '@/components/ui/ErrorState';
import LoadingState from '@/components/ui/LoadingState';
import Table from '@/components/ui/Table';
import Toast from '@/components/ui/Toast';
import { getArticleScores } from '@/api/content';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { useWorkspace } from '@/hooks/useWorkspace';
import { optionLabel } from '@/utils/options';
import LatestScores from '@/pages/content/articles/components/LatestScores';
import ScoreFormModal from '@/pages/content/articles/components/ScoreFormModal';

export default function ArticleScores({ client, article }) {
    const { options, workspaceSlug } = useWorkspace();
    const { formatDate } = useDateFormatter();
    const [scores, setScores] = useState(null);
    const [loadError, setLoadError] = useState(null);
    const [adding, setAdding] = useState(false);
    const [message, setMessage] = useState(null);

    const load = useCallback(() => {
        getArticleScores(workspaceSlug, client.id, article.id)
            .then((response) => setScores(response.data || []))
            .catch((error) => setLoadError(error.message));
    }, [workspaceSlug, client.id, article.id]);

    useEffect(() => {
        load();
    }, [load]);

    const latest = scores?.[0];
    const latestOf = (type) => scores?.find((score) => score.score_type === type) || null;
    const categories = options.article_score_categories || [];

    return (
        <Card title="Scores" action={article.can?.score ? <Button icon={Plus} variant="secondary" onClick={() => setAdding(true)}>Add score</Button> : null}>
            <div id="scores" className="form-stack card-pad">
                <Toast message={message} />
                {loadError ? <ErrorState title="Could not load scores" copy={loadError} /> : null}
                {!scores && !loadError ? <LoadingState label="Loading scores" /> : null}
                {scores && !scores.length ? <EmptyState icon={BarChart3} title="Not scored yet" copy="AI and human scores are kept here with their history." /> : null}
                {scores?.length ? (
                    <>
                        <LatestScores ai={latestOf('ai')} human={latestOf('human')} />
                        <div>
                            <h3 className="card-title">Category breakdown</h3>
                            <p className="field-hint">From the {optionLabel(options.article_score_types, latest.score_type)} score on {formatDate(latest.created_at)}.</p>
                            <ul className="list">
                                {categories.map(({ value, label }) => (
                                    <li className="between-row score-row" key={value}>
                                        <span>{label}</span>
                                        <strong>{latest.category_scores[value] ?? '—'}</strong>
                                    </li>
                                ))}
                            </ul>
                        </div>
                        {latest.feedback ? (
                            <div>
                                <h3 className="card-title">Feedback</h3>
                                <p className="section-copy preserve-lines">{latest.feedback}</p>
                            </div>
                        ) : null}
                        <div>
                            <h3 className="card-title">Score history</h3>
                            <Table
                                columns={[
                                    { key: 'date', label: 'Date', render: (score) => formatDate(score.created_at) },
                                    { key: 'type', label: 'Type', render: (score) => <Badge status={score.score_type === 'human' ? 'active' : 'default'}>{optionLabel(options.article_score_types, score.score_type)}</Badge> },
                                    { key: 'score', label: 'Score', render: (score) => <strong>{score.overall_score}</strong> },
                                    { key: 'by', label: 'Scored by', render: (score) => score.scorer?.name || <span className="table-muted">—</span> },
                                ]}
                                rows={scores}
                                rowKey={(score) => score.id}
                            />
                        </div>
                    </>
                ) : null}
            </div>
            {adding ? (
                <ScoreFormModal
                    client={client}
                    article={article}
                    onClose={() => setAdding(false)}
                    onSaved={() => {
                        setAdding(false);
                        setMessage('Score added.');
                        load();
                    }}
                />
            ) : null}
        </Card>
    );
}
