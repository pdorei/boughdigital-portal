import React from 'react';
import { CheckCircle2 } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import EmptyState from '@/components/ui/EmptyState';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { labelize } from '@/utils/text';

export default function TaskList({ tasks, emptyCopy = 'Tasks will appear here when work is created.' }) {
    const { formatDate } = useDateFormatter();

    if (!tasks?.length) {
        return <EmptyState icon={CheckCircle2} title="No tasks" copy={emptyCopy} />;
    }

    return (
        <div className="list">
            {tasks.map((task) => (
                <div className="list-row" key={task.id}>
                    <div className="flex items-center justify-between gap-3">
                        <strong>{task.title}</strong>
                        <Badge status={task.status}>{task.status}</Badge>
                    </div>
                    <span className="muted">{task.client || 'Workspace'} / {formatDate(task.due_date, 'No due date')} / {labelize(task.priority || 'normal')}</span>
                </div>
            ))}
        </div>
    );
}
