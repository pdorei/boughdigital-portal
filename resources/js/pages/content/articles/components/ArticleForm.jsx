import React, { useState } from 'react';
import { CalendarDays, FileText, Link2, Save, Search, Type, X } from 'lucide-react';
import Input from '@/components/forms/Input';
import Select from '@/components/forms/Select';
import TagInput from '@/components/forms/TagInput';
import Textarea from '@/components/forms/Textarea';
import Button from '@/components/ui/Button';
import ErrorState from '@/components/ui/ErrorState';
import { createArticle, updateArticle } from '@/api/content';
import { useWorkspace } from '@/hooks/useWorkspace';
import { flash } from '@/utils/flash';

function Section({ title, description, children }) {
    return (
        <div className="form-section">
            <div>
                <h3>{title}</h3>
                {description ? <p>{description}</p> : null}
            </div>
            {children}
        </div>
    );
}

/**
 * Shared by the create and edit pages. `prefill` carries a calendar entry the
 * new article is written for; saving links the two.
 */
export default function ArticleForm({ client, article = null, prefill = null, pillars, writers, cancelUrl, articlesUrl }) {
    const { options, workspaceSlug } = useWorkspace();
    const source = article || prefill || {};
    const [keywords, setKeywords] = useState(source.secondary_keywords || []);
    const [tags, setTags] = useState(article?.tags || []);
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState({});
    const [formError, setFormError] = useState(null);
    const isEditing = Boolean(article?.id);
    const error = (key) => errors[key]?.[0];
    const pillarOptions = pillars.filter((pillar) => pillar.active || pillar.value === source.content_pillar_id);

    async function handleSubmit(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        setFormError(null);

        const form = new FormData(event.currentTarget);
        const value = (name) => form.get(name) || null;
        const payload = {
            title: form.get('title'),
            content_pillar_id: value('content_pillar_id'),
            status: form.get('status'),
            assigned_writer_id: value('assigned_writer_id'),
            primary_keyword: value('primary_keyword'),
            secondary_keywords: keywords,
            search_intent: value('search_intent'),
            slug: value('slug'),
            meta_title: value('meta_title'),
            meta_description: value('meta_description'),
            canonical_url: value('canonical_url'),
            content_body: value('content_body'),
            excerpt: value('excerpt'),
            published_url: value('published_url'),
            published_at: value('published_at'),
            tags,
            ...(isEditing ? {} : { calendar_entry_id: prefill?.id || null }),
        };

        try {
            const response = isEditing
                ? await updateArticle(workspaceSlug, client.id, article.id, payload)
                : await createArticle(workspaceSlug, client.id, payload);
            flash(isEditing ? 'Article updated successfully.' : 'Article created successfully.');
            window.location.assign(`${articlesUrl}/${response.data.id}`);
        } catch (saveError) {
            setErrors(saveError.errors);
            setFormError(saveError.message);
            setSubmitting(false);
        }
    }

    return (
        <form className="form-stack" onSubmit={handleSubmit}>
            {formError ? <ErrorState title="Could not save article" copy={formError} /> : null}
            {prefill ? <p className="status-message">This article will be linked to the calendar entry “{prefill.title}”.</p> : null}
            <Section title="Basic information">
                <Input icon={Type} name="title" label="Title *" defaultValue={source.title || ''} error={error('title')} required />
                <div className="form-row">
                    <Select
                        name="content_pillar_id"
                        label="Content pillar"
                        defaultValue={source.content_pillar_id ?? ''}
                        options={[{ value: '', label: 'No pillar' }, ...pillarOptions]}
                        error={error('content_pillar_id')}
                    />
                    <Select name="assigned_writer_id" label="Writer" defaultValue={source.assigned_writer_id ?? ''} options={[{ value: '', label: 'Unassigned' }, ...writers]} error={error('assigned_writer_id')} />
                </div>
                <div className="form-row">
                    <Select name="status" label="Status" defaultValue={article?.status || 'draft'} options={options.article_statuses || []} error={error('status')} />
                </div>
            </Section>
            <Section title="SEO">
                <div className="form-row">
                    <Input icon={Search} name="primary_keyword" label="Primary keyword" defaultValue={source.primary_keyword || source.target_keyword || ''} error={error('primary_keyword')} />
                    <Select name="search_intent" label="Search intent" defaultValue={source.search_intent || ''} options={[{ value: '', label: 'Not specified' }, ...(options.search_intents || [])]} error={error('search_intent')} />
                </div>
                <TagInput label="Secondary keywords" values={keywords} onChange={setKeywords} max={20} error={error('secondary_keywords')} />
                <div className="form-row">
                    <Input icon={Type} name="meta_title" label="Meta title" defaultValue={article?.meta_title || ''} error={error('meta_title')} />
                    <Input icon={Link2} name="slug" label="Slug" defaultValue={article?.slug || ''} placeholder="best-accounting-software" error={error('slug')} />
                </div>
                <Textarea name="meta_description" label="Meta description" rows={3} defaultValue={article?.meta_description || ''} error={error('meta_description')} />
                <Input icon={Link2} name="canonical_url" type="url" label="Canonical URL" defaultValue={article?.canonical_url || ''} error={error('canonical_url')} />
            </Section>
            <Section title="Content" description="The word count is calculated from the article body when you save.">
                <Textarea name="content_body" label="Article body" rows={18} defaultValue={article?.content_body || ''} error={error('content_body')} />
                <Textarea name="excerpt" label="Excerpt" rows={3} defaultValue={article?.excerpt || ''} error={error('excerpt')} />
            </Section>
            <Section title="Publishing">
                <div className="form-row">
                    <Input icon={Link2} name="published_url" type="url" label="Published URL" defaultValue={article?.published_url || ''} error={error('published_url')} />
                    <Input icon={CalendarDays} name="published_at" type="date" label="Published date" defaultValue={article?.published_at?.slice(0, 10) || ''} error={error('published_at')} />
                </div>
            </Section>
            <Section title="Tags">
                <TagInput label="Tags" values={tags} onChange={setTags} max={30} error={error('tags')} />
            </Section>
            <div className="inline-actions action-group-end">
                <Button icon={X} variant="secondary" href={cancelUrl}>Cancel</Button>
                <Button icon={isEditing ? Save : FileText} type="submit" disabled={submitting}>{submitting ? 'Saving...' : 'Save article'}</Button>
            </div>
        </form>
    );
}
