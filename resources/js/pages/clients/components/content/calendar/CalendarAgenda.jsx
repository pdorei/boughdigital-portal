import React from 'react';
import { CalendarDays } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import EmptyState from '@/components/ui/EmptyState';
import RowActions from '@/components/ui/RowActions';
import { useWorkspace } from '@/hooks/useWorkspace';
import { formatDayMonth, formatMonthYear } from '@/utils/date';
import { optionLabel } from '@/utils/options';

/**
 * Entries grouped by publish month, in the order the API returns them
 * (earliest first, unscheduled last).
 */
export default function CalendarAgenda({ entries, actions, onView }) {
    const { options } = useWorkspace();

    if (!entries.length) {
        return <EmptyState icon={CalendarDays} title="Nothing planned" copy="Add the articles and pages this client should publish, with keywords, writers and dates." />;
    }

    const months = entries.reduce((groups, entry) => {
        const label = entry.publish_date ? formatMonthYear(entry.publish_date) : 'Not scheduled';
        const group = groups.find((item) => item.label === label);

        if (group) group.entries.push(entry);
        else groups.push({ label, entries: [entry] });

        return groups;
    }, []);

    return (
        <div className="form-stack">
            {months.map((month) => (
                <section key={month.label} className="agenda-month">
                    <h3 className="card-title">{month.label}</h3>
                    <ul className="list">
                        {month.entries.map((entry) => (
                            <li key={entry.id} className="agenda-row">
                                <span className="agenda-date">{formatDayMonth(entry.publish_date)}</span>
                                <span className="agenda-body">
                                    <button type="button" className="client-link link-button" onClick={() => onView(entry)}>{entry.title}</button>
                                    <span className="table-muted">
                                        {[entry.target_keyword ? `Keyword: ${entry.target_keyword}` : null, `Writer: ${entry.writer?.name || 'Unassigned'}`].filter(Boolean).join(' · ')}
                                    </span>
                                </span>
                                <Badge status={entry.status}>{optionLabel(options.content_calendar_statuses, entry.status)}</Badge>
                                <RowActions label={`Open actions for ${entry.title}`} actions={actions(entry)} />
                            </li>
                        ))}
                    </ul>
                </section>
            ))}
        </div>
    );
}
