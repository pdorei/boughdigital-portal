import React from 'react';
import Card from '@/components/ui/Card';
import PageHeader from '@/components/layout/PageHeader';
import { useWorkspace } from '@/hooks/useWorkspace';
import ArticleForm from '@/pages/content/articles/components/ArticleForm';

export default function ArticleCreate({ base }) {
    const { pageData } = useWorkspace();
    const client = pageData.client;
    const contentUrl = `${base}/clients/${client.slug}?tab=content&section=articles`;

    return (
        <div className="page-stack">
            <PageHeader eyebrow={`${client.name} · Content`} title="New article" description="Write the article and its SEO details. Scores can be added once it is saved." />
            <Card padded>
                <ArticleForm
                    client={client}
                    prefill={pageData.calendar_entry}
                    pillars={pageData.pillars || []}
                    writers={pageData.writers || []}
                    cancelUrl={contentUrl}
                    articlesUrl={`${base}/clients/${client.slug}/articles`}
                />
            </Card>
        </div>
    );
}
