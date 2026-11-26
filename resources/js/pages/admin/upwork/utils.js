import { optionLabel } from '@/utils/options';

export function money(value, decimals = 0) {
    if (value === null || value === undefined) return null;

    return `$${Number(value).toLocaleString('en-AU', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })}`;
}

export function budgetLabel(job) {
    if (job.job_type === 'hourly') {
        if (job.hourly_min === null && job.hourly_max === null) return 'Hourly, rate not stated';
        if (job.hourly_min !== null && job.hourly_max !== null && job.hourly_min !== job.hourly_max) return `${money(job.hourly_min)} to ${money(job.hourly_max)}/hr`;

        return `${money(job.hourly_max ?? job.hourly_min)}/hr`;
    }

    return job.fixed_budget ? `${money(job.fixed_budget)} fixed` : 'Fixed, budget not stated';
}

export function bidLabel(proposal) {
    if (proposal.bid_amount === null) return 'Bid not set';

    return proposal.bid_type === 'hourly' ? `${money(proposal.bid_amount, 2)}/hr` : `${money(proposal.bid_amount)} fixed`;
}

/**
 * "12 min ago" style age, for how fresh a job is. Exact times use formatDateTime.
 */
export function ageLabel(value) {
    if (!value) return null;

    const minutes = Math.max(0, Math.round((Date.now() - new Date(value).getTime()) / 60000));

    if (minutes < 60) return `${minutes} min ago`;
    if (minutes < 60 * 48) return `${Math.round(minutes / 60)} h ago`;

    return `${Math.round(minutes / 1440)} days ago`;
}

export function clientSummary(client = {}) {
    return [
        client.country,
        client.payment_verified === null || client.payment_verified === undefined ? null : (client.payment_verified ? 'Payment verified' : 'Payment not verified'),
        client.total_spent ? `${money(client.total_spent)} spent` : null,
        client.hire_rate !== null && client.hire_rate !== undefined ? `${Math.round(client.hire_rate)}% hire rate` : null,
        client.rating ? `${Number(client.rating).toFixed(1)} rating` : null,
    ].filter(Boolean).join(' · ') || 'No client details';
}

export function statusLabel(options, key, value) {
    return optionLabel(options[key] || [], value);
}

/**
 * Plain text for "Copy all": cover letter, bid and screening answers.
 */
export function proposalClipboardText(proposal) {
    const parts = [proposal.cover_letter || ''];

    parts.push(`\nBid: ${bidLabel(proposal)}`);

    (proposal.screening_answers || []).forEach((item, index) => {
        parts.push(`\nQ${index + 1}. ${item.question}\n${item.answer || ''}`);
    });

    return parts.join('\n').trim();
}

export async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text);

        return true;
    } catch {
        // Clipboard API unavailable (http or permissions): fall back to a hidden textarea.
        const area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        const copied = document.execCommand('copy');
        document.body.removeChild(area);

        return copied;
    }
}

export function wordCount(text) {
    return (text || '').trim().split(/\s+/).filter(Boolean).length;
}
