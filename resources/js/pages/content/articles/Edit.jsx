import React from 'react';
import Card from '@/components/ui/Card';
import PageHeader from '@/components/layout/PageHeader';
import { useWorkspace } from '@/hooks/useWorkspace';
import ArticleForm from '@/pages/content/articles/components/ArticleForm';

export default function ArticleEdit({ base }) {
    const { pageData } = useWorkspace();
    const { client, article } = pageData;
    const articlesUrl = `${base}/clients/${client.slug}/articles`;

    return (
        <div className="page-stack">
            <PageHeader eyebrow={`${client.name} · Content`} title={`Edit ${article.title}`} />
            <Card padded>
                <ArticleForm
                    client={client}
                    article={article}
                    pillars={pageData.pillars || []}
                    writers={pageData.writers || []}
                    cancelUrl={`${articlesUrl}/${article.id}`}
                    articlesUrl={articlesUrl}
                />
            </Card>
        </div>
    );
}
