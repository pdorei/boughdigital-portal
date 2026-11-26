export function statusTone(status) {
    if (['active', 'completed', 'published', 'ready', 'connected', 'approved', 'scheduled', 'accepted', 'sent', 'proposal_sent'].includes(status)) {
        return 'success';
    }

    if (['on_hold', 'backlog', 'draft', 'paused', 'high', 'urgent', 'error', 'rate_limited', 'failed'].includes(status)) {
        return 'warning';
    }

    if (['next_up', 'in_progress', 'assigned', 'writing', 'review', 'running'].includes(status)) {
        return 'info';
    }

    return 'default';
}
