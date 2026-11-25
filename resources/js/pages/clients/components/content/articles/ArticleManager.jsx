import React, { useEffect, useState } from 'react';
import { Plus } from 'lucide-react';
import FilterBar from '@/components/forms/FilterBar';
import SearchInput from '@/components/forms/SearchInput';
import Select from '@/components/forms/Select';
import Button from '@/components/ui/Button';
import ErrorState from '@/components/ui/ErrorState';
import LoadingState from '@/components/ui/LoadingState';
import Pagination from '@/components/ui/Pagination';
import Toast from '@/components/ui/Toast';
import { deleteArticle, getArticles, getPillars } from '@/api/content';
import { usePaginatedList } from '@/hooks/usePaginatedList';
import { useWorkspace } from '@/hooks/useWorkspace';
import ArticleTable from '@/pages/clients/components/content/articles/ArticleTable';

/**
 * "What content have we actually created?" — the client's article library.
 * Articles open on their own pages for writing and scoring.
 */
export default function ArticleManager({ client, base, writers }) {
    const { options, workspaceSlug } = useWorkspace();
    const [pillars, setPillars] = useState([]);
    const [feedback, setFeedback] = useState({ message: null, tone: 'success' });
    const list = usePaginatedList((params) => getArticles(workspaceSlug, client.id, params), {
        initialFilters: { search: '', status: '', pillar: '', writer: '' },
    });
    const articlesUrl = `${base}/clients/${client.slug}/articles`;

    useEffect(() => {
        getPillars(workspaceSlug, client.id).then((response) => setPillars(response.data || [])).catch(() => setPillars([]));
    }, [workspaceSlug, client.id]);

    async function remove(article) {
        try {
            await deleteArticle(workspaceSlug, client.id, article.id);
            setFeedback({ message: 'Article deleted.', tone: 'success' });
            list.reload();
        } catch (error) {
            setFeedback({ message: error.message, tone: 'error' });
        }
    }

    return (
        <div className="form-stack">
            <Toast message={feedback.message} tone={feedback.tone} />
            <FilterBar>
                <SearchInput value={list.filters.search} onChange={(value) => list.setFilter('search', value)} placeholder="Search titles and keywords" />
                <Select label="Status" value={list.filters.status} onChange={(event) => list.setFilter('status', event.target.value)} options={[{ value: '', label: 'Any status' }, ...(options.article_statuses || [])]} />
                <Select label="Pillar" value={list.filters.pillar} onChange={(event) => list.setFilter('pillar', event.target.value)} options={[{ value: '', label: 'All pillars' }, ...pillars.map((pillar) => ({ value: pillar.id, label: pillar.name }))]} />
                <Select label="Writer" value={list.filters.writer} onChange={(event) => list.setFilter('writer', event.target.value)} options={[{ value: '', label: 'Anyone' }, { value: 'me', label: 'Assigned to me' }, ...writers]} />
                {list.meta?.can_create ? <Button icon={Plus} variant="accent" href={`${articlesUrl}/create`}>New article</Button> : null}
            </FilterBar>
            {list.error ? <ErrorState title="Could not load articles" copy={list.error} /> : null}
            {list.loading ? <LoadingState label="Loading articles" /> : (
                <>
                    <ArticleTable articles={list.rows} articleUrl={(id) => `${articlesUrl}/${id}`} onDelete={remove} />
                    <Pagination meta={list.meta} onPageChange={list.setPage} />
                </>
            )}
        </div>
    );
}
