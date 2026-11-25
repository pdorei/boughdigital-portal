export function statusTone(status) {
    if (['active', 'completed', 'published', 'ready', 'connected', 'approved', 'scheduled'].includes(status)) {
        return 'success';
    }

    if (['on_hold', 'backlog', 'draft', 'paused', 'high', 'urgent', 'error'].includes(status)) {
        return 'warning';
    }

    if (['next_up', 'in_progress', 'assigned', 'writing', 'review'].includes(status)) {
        return 'info';
    }

    return 'default';
}
