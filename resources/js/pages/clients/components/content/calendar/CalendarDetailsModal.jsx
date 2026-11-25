import React from 'react';
import { CalendarDays, Pencil, Search } from 'lucide-react';
import DisplayField from '@/components/forms/DisplayField';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import Modal from '@/components/ui/Modal';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { useWorkspace } from '@/hooks/useWorkspace';
import { optionLabel } from '@/utils/options';
import PillarName from '@/pages/clients/components/content/PillarName';

export default function CalendarDetailsModal({ entry, articleUrl, onClose, onEdit }) {
    const { formatDate } = useDateFormatter();
    const { options } = useWorkspace();

    return (
        <Modal title="View calendar entry" onClose={onClose} wide>
            <div className="form-stack">
                <DisplayField icon={CalendarDays} label="Title">{entry.title}</DisplayField>
                <div className="form-row">
                    <DisplayField icon={Search} label="Target keyword">{entry.target_keyword || 'Not set'}</DisplayField>
                    <DisplayField label="Search intent">{entry.search_intent ? optionLabel(options.search_intents, entry.search_intent) : 'Not specified'}</DisplayField>
                </div>
                <DisplayField label="Secondary keywords">
                    {entry.secondary_keywords.length ? (
                        <span className="tag-list">{entry.secondary_keywords.map((keyword) => <span className="tag-chip tag-chip-static" key={keyword}>{keyword}</span>)}</span>
                    ) : 'None'}
                </DisplayField>
                <div className="form-row">
                    <DisplayField label="Content type">{optionLabel(options.content_types, entry.content_type)}</DisplayField>
                    <DisplayField label="Status"><Badge status={entry.status}>{optionLabel(options.content_calendar_statuses, entry.status)}</Badge></DisplayField>
                </div>
                <div className="form-row">
                    <DisplayField label="Content pillar"><PillarName pillar={entry.pillar} /></DisplayField>
                    <DisplayField label="Writer">{entry.writer?.name || 'Unassigned'}</DisplayField>
                </div>
                <div className="form-row">
                    <DisplayField icon={CalendarDays} label="Due date">{formatDate(entry.due_date, 'No due date')}</DisplayField>
                    <DisplayField icon={CalendarDays} label="Publish date">{formatDate(entry.publish_date, 'Not scheduled')}</DisplayField>
                </div>
                <DisplayField label="Linked article">
                    {entry.article ? <a className="link" href={articleUrl(entry.article.id)}>{entry.article.title}</a> : 'No article yet'}
                </DisplayField>
                <DisplayField label="Notes" multiline>{entry.notes || 'No notes.'}</DisplayField>
                {entry.can?.update ? (
                    <div className="inline-actions action-group-end">
                        <Button icon={Pencil} variant="secondary" onClick={() => onEdit(entry)}>Edit entry</Button>
                    </div>
                ) : null}
            </div>
        </Modal>
    );
}
