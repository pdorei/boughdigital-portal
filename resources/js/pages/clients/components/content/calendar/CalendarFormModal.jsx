import React, { useEffect, useState } from 'react';
import { CalendarDays, Save, Search } from 'lucide-react';
import Input from '@/components/forms/Input';
import Select from '@/components/forms/Select';
import TagInput from '@/components/forms/TagInput';
import Textarea from '@/components/forms/Textarea';
import Button from '@/components/ui/Button';
import ErrorState from '@/components/ui/ErrorState';
import Modal from '@/components/ui/Modal';
import { createCalendarEntry, getArticles, updateCalendarEntry } from '@/api/content';
import { useWorkspace } from '@/hooks/useWorkspace';

export default function CalendarFormModal({ client, entry = null, pillars, writers, onClose, onSaved }) {
    const { options, workspaceSlug } = useWorkspace();
    const [keywords, setKeywords] = useState(entry?.secondary_keywords || []);
    const [articles, setArticles] = useState([]);
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState({});
    const [formError, setFormError] = useState(null);
    const isEditing = Boolean(entry?.id);
    const error = (key) => errors[key]?.[0];

    useEffect(() => {
        getArticles(workspaceSlug, client.id, { per_page: 100 })
            .then((response) => setArticles(response.data || []))
            .catch(() => setArticles([]));
    }, [workspaceSlug, client.id]);

    // Inactive pillars stay selectable only for entries that already use them.
    const pillarOptions = pillars.filter((pillar) => pillar.active || pillar.id === entry?.content_pillar_id);

    async function handleSubmit(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        setFormError(null);

        const form = new FormData(event.currentTarget);
        const payload = {
            title: form.get('title'),
            target_keyword: form.get('target_keyword') || null,
            secondary_keywords: keywords,
            content_type: form.get('content_type'),
            status: form.get('status'),
            search_intent: form.get('search_intent') || null,
            content_pillar_id: form.get('content_pillar_id') || null,
            assigned_writer_id: form.get('assigned_writer_id') || null,
            article_id: form.get('article_id') || null,
            due_date: form.get('due_date') || null,
            publish_date: form.get('publish_date') || null,
            notes: form.get('notes') || null,
        };

        try {
            const response = isEditing
                ? await updateCalendarEntry(workspaceSlug, client.id, entry.id, payload)
                : await createCalendarEntry(workspaceSlug, client.id, payload);
            onSaved(response.data, isEditing ? 'Calendar entry updated successfully.' : 'Calendar entry added successfully.');
        } catch (saveError) {
            setErrors(saveError.errors);
            setFormError(saveError.message);
            setSubmitting(false);
        }
    }

    return (
        <Modal title={isEditing ? 'Edit calendar entry' : 'Plan content'} onClose={onClose} busy={submitting} wide>
            <form className="form-stack" onSubmit={handleSubmit}>
                {formError ? <ErrorState title="Could not save calendar entry" copy={formError} /> : null}
                <Input icon={CalendarDays} name="title" label="Title *" defaultValue={entry?.title || ''} error={error('title')} required />
                <div className="form-row">
                    <Input icon={Search} name="target_keyword" label="Target keyword" defaultValue={entry?.target_keyword || ''} error={error('target_keyword')} />
                    <Select name="search_intent" label="Search intent" defaultValue={entry?.search_intent || ''} options={[{ value: '', label: 'Not specified' }, ...(options.search_intents || [])]} error={error('search_intent')} />
                </div>
                <TagInput label="Secondary keywords" values={keywords} onChange={setKeywords} max={20} error={error('secondary_keywords')} />
                <div className="form-row">
                    <Select name="content_type" label="Content type" defaultValue={entry?.content_type || 'article'} options={options.content_types || []} error={error('content_type')} />
                    <Select name="status" label="Status" defaultValue={entry?.status || 'planned'} options={options.content_calendar_statuses || []} error={error('status')} />
                </div>
                <div className="form-row">
                    <Select
                        name="content_pillar_id"
                        label="Content pillar"
                        defaultValue={entry?.content_pillar_id ?? ''}
                        options={[{ value: '', label: 'No pillar' }, ...pillarOptions.map((pillar) => ({ value: pillar.id, label: pillar.name }))]}
                        error={error('content_pillar_id')}
                    />
                    <Select name="assigned_writer_id" label="Writer" defaultValue={entry?.assigned_writer_id ?? ''} options={[{ value: '', label: 'Unassigned' }, ...writers]} error={error('assigned_writer_id')} />
                </div>
                <div className="form-row">
                    <Input icon={CalendarDays} name="due_date" type="date" label="Due date" defaultValue={entry?.due_date || ''} error={error('due_date')} />
                    <Input icon={CalendarDays} name="publish_date" type="date" label="Publish date" defaultValue={entry?.publish_date || ''} error={error('publish_date')} />
                </div>
                <Select
                    key={articles.length}
                    name="article_id"
                    label="Linked article"
                    defaultValue={entry?.article_id ?? ''}
                    options={[{ value: '', label: 'No article yet' }, ...articles.map((article) => ({ value: article.id, label: article.title }))]}
                    error={error('article_id')}
                />
                <Textarea name="notes" label="Notes" rows={4} defaultValue={entry?.notes || ''} error={error('notes')} />
                <div className="inline-actions action-group-end">
                    <Button variant="secondary" onClick={onClose} disabled={submitting}>Cancel</Button>
                    <Button icon={Save} type="submit" disabled={submitting}>{submitting ? 'Saving...' : 'Save entry'}</Button>
                </div>
            </form>
        </Modal>
    );
}
