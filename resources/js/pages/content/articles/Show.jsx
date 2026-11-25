import React from 'react';
import { ArrowLeft, CalendarDays, Link2, Pencil, Search } from 'lucide-react';
import DisplayField from '@/components/forms/DisplayField';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import Card from '@/components/ui/Card';
import PageHeader from '@/components/layout/PageHeader';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { useWorkspace } from '@/hooks/useWorkspace';
import { formatNumber } from '@/utils/formatNumber';
import { optionLabel } from '@/utils/options';
import PillarName from '@/pages/clients/components/content/PillarName';
import ArticleScores from '@/pages/content/articles/components/ArticleScores';

function Chips({ values, empty }) {
    return values.length
        ? <span className="tag-list">{values.map((value) => <span className="tag-chip tag-chip-static" key={value}>{value}</span>)}</span>
        : empty;
}

function ExternalLink({ url }) {
    return url ? <a className="link" href={url} target="_blank" rel="noopener noreferrer">{url}</a> : 'Not set';
}

export default function ArticleShow({ base }) {
    const { pageData, options } = useWorkspace();
    const { formatDate } = useDateFormatter();
    const { client, article } = pageData;
    const articleUrl = `${base}/clients/${client.slug}/articles/${article.id}`;

    return (
        <div className="page-stack">
            <PageHeader
                eyebrow={`${client.name} · Content`}
                title={article.title}
                action={(
                    <div className="inline-actions">
                        <Button icon={ArrowLeft} variant="secondary" href={`${base}/clients/${client.slug}?tab=content&section=articles`}>All articles</Button>
                        {article.can?.update ? <Button icon={Pencil} variant="secondary" href={`${articleUrl}/edit`}>Edit</Button> : null}
                    </div>
                )}
            />
            <div className="grid-two">
                <div className="form-stack">
                    <Card title="Article" padded>
                        <div className="form-stack">
                            <div className="form-row">
                                <DisplayField label="Status"><Badge status={article.status}>{optionLabel(options.article_statuses, article.status)}</Badge></DisplayField>
                                <DisplayField label="Content pillar"><PillarName pillar={article.pillar} /></DisplayField>
                            </div>
                            <div className="form-row">
                                <DisplayField label="Writer">{article.writer?.name || 'Unassigned'}</DisplayField>
                                <DisplayField label="Word count">{article.word_count ? formatNumber(article.word_count) : 'Not counted'}</DisplayField>
                            </div>
                            <div className="form-row">
                                <DisplayField icon={Search} label="Primary keyword">{article.primary_keyword || 'Not set'}</DisplayField>
                                <DisplayField label="Search intent">{article.search_intent ? optionLabel(options.search_intents, article.search_intent) : 'Not specified'}</DisplayField>
                            </div>
                            <DisplayField label="Secondary keywords"><Chips values={article.secondary_keywords} empty="None" /></DisplayField>
                            <DisplayField label="Meta title">{article.meta_title || 'Not set'}</DisplayField>
                            <DisplayField label="Meta description" multiline>{article.meta_description || 'Not set.'}</DisplayField>
                            <div className="form-row">
                                <DisplayField icon={Link2} label="Canonical URL"><ExternalLink url={article.canonical_url} /></DisplayField>
                                <DisplayField icon={Link2} label="Published URL"><ExternalLink url={article.published_url} /></DisplayField>
                            </div>
                            <div className="form-row">
                                <DisplayField icon={CalendarDays} label="Published date">{formatDate(article.published_at, 'Not published')}</DisplayField>
                                <DisplayField icon={CalendarDays} label="Calendar entry">{article.calendar_entry ? article.calendar_entry.title : 'Not planned in the calendar'}</DisplayField>
                            </div>
                            <DisplayField label="Tags"><Chips values={article.tags} empty="No tags" /></DisplayField>
                        </div>
                    </Card>
                    <Card title="Content" padded>
                        <div className="form-stack">
                            <DisplayField label="Excerpt" multiline>{article.excerpt || 'No excerpt.'}</DisplayField>
                            <DisplayField label="Article body" multiline>{article.content_body || 'Nothing written yet.'}</DisplayField>
                        </div>
                    </Card>
                </div>
                <div className="form-stack">
                    <ArticleScores client={client} article={article} />
                </div>
            </div>
        </div>
    );
}
