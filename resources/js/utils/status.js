export function statusTone(status) {
    if (['active', 'completed', 'published', 'ready'].includes(status)) {
        return 'success';
    }

    if (['on_hold', 'backlog', 'draft', 'paused', 'high', 'urgent'].includes(status)) {
        return 'warning';
    }

    if (['next_up', 'in_progress'].includes(status)) {
        return 'info';
    }

    return 'default';
}
