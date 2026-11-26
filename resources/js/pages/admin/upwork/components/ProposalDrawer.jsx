import React, { useEffect, useState } from 'react';
import { Check, Copy, DollarSign, Save, X } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import Drawer from '@/components/ui/Drawer';
import ErrorState from '@/components/ui/ErrorState';
import LoadingState from '@/components/ui/LoadingState';
import Modal from '@/components/ui/Modal';
import Input from '@/components/forms/Input';
import Textarea from '@/components/forms/Textarea';
import {
    approveUpworkProposal,
    getUpworkProposal,
    markUpworkProposalSent,
    rejectUpworkProposal,
    updateUpworkProposal,
} from '@/api/upwork';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { useWorkspace } from '@/hooks/useWorkspace';
import JobFacts from '@/pages/admin/upwork/components/JobFacts';
import { copyText, proposalClipboardText, statusLabel, wordCount } from '@/pages/admin/upwork/utils';

function draftFrom(proposal) {
    return {
        bid_amount: proposal.bid_amount ?? '',
        cover_letter: proposal.cover_letter || '',
        screening_answers: (proposal.screening_answers || []).map((item) => ({ question: item.question, answer: item.answer || '' })),
    };
}

function isDirty(proposal, draft) {
    return JSON.stringify(draftFrom(proposal)) !== JSON.stringify({ ...draft, bid_amount: draft.bid_amount === '' ? '' : Number(draft.bid_amount) });
}

/**
 * Review and act on one proposal. Nothing here submits to Upwork: "Copy all
 * and open in Upwork" copies the text and opens the job so a person can paste
 * and send it, then records that it was sent.
 */
export default function ProposalDrawer({ proposalId, onClose, onChanged }) {
    const { options } = useWorkspace();
    const { formatDateTime } = useDateFormatter();
    const [proposal, setProposal] = useState(null);
    const [draft, setDraft] = useState(null);
    const [loadError, setLoadError] = useState(null);
    const [errors, setErrors] = useState({});
    const [error, setError] = useState(null);
    const [busy, setBusy] = useState(null);
    const [rejecting, setRejecting] = useState(false);
    const [rejectReason, setRejectReason] = useState('');
    const [askSent, setAskSent] = useState(false);
    const [connects, setConnects] = useState('');
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        getUpworkProposal(proposalId)
            .then((response) => {
                setProposal(response.data);
                setDraft(draftFrom(response.data));
            })
            .catch((apiError) => setLoadError(apiError.message));
    }, [proposalId]);

    function apply(response) {
        setProposal(response.data);
        setDraft(draftFrom(response.data));
        setErrors({});
        setError(null);
        onChanged?.(response.data, response.message);
    }

    async function run(name, action) {
        setBusy(name);
        setError(null);
        try {
            return await action();
        } catch (apiError) {
            setErrors(apiError.errors || {});
            setError(apiError.message);

            return null;
        } finally {
            setBusy(null);
        }
    }

    function payload() {
        return {
            bid_amount: draft.bid_amount === '' ? null : Number(draft.bid_amount),
            cover_letter: draft.cover_letter,
            screening_answers: draft.screening_answers,
        };
    }

    const save = () => run('save', async () => {
        const response = await updateUpworkProposal(proposal.id, payload());
        apply(response);

        return response;
    });

    const approve = () => run('approve', async () => {
        if (isDirty(proposal, draft)) {
            await updateUpworkProposal(proposal.id, payload());
        }
        apply(await approveUpworkProposal(proposal.id));
    });

    const reject = () => run('reject', async () => {
        apply(await rejectUpworkProposal(proposal.id, rejectReason.trim() || null));
        setRejecting(false);
    });

    const copyAndOpen = () => run('copy', async () => {
        const ok = await copyText(proposalClipboardText(proposal));
        setCopied(ok);
        if (!ok) setError('Could not copy to the clipboard. Select the text and copy it manually.');
        window.open(proposal.job.url, '_blank', 'noopener,noreferrer');
        setAskSent(true);
    });

    const markSent = () => run('sent', async () => {
        apply(await markUpworkProposalSent(proposal.id, connects === '' ? null : Number(connects)));
        setAskSent(false);
    });

    const can = proposal?.can || {};
    const dirty = proposal && draft ? isDirty(proposal, draft) : false;
    const words = wordCount(draft?.cover_letter);

    return (
        <Drawer
            title={proposal?.job?.title || 'Proposal'}
            subtitle={proposal ? <Badge status={proposal.status}>{statusLabel(options, 'upwork_proposal_statuses', proposal.status)}</Badge> : null}
            onClose={onClose}
            busy={Boolean(busy)}
            footer={proposal ? (
                <div className="drawer-actions">
                    {can.edit ? <Button variant="secondary" icon={Save} onClick={save} disabled={!dirty || Boolean(busy)}>{busy === 'save' ? 'Saving...' : 'Save changes'}</Button> : null}
                    {can.approve ? <Button icon={Check} onClick={approve} disabled={Boolean(busy)}>{busy === 'approve' ? 'Approving...' : dirty ? 'Save and approve' : 'Approve'}</Button> : null}
                    {can.send ? <Button icon={Copy} onClick={copyAndOpen} disabled={Boolean(busy) || dirty}>Copy all and open in Upwork</Button> : null}
                    {can.reject ? <Button variant="danger" icon={X} onClick={() => setRejecting(true)} disabled={Boolean(busy)}>Reject</Button> : null}
                </div>
            ) : null}
        >
            {loadError ? <ErrorState title="Could not load the proposal" copy={loadError} /> : null}
            {!proposal && !loadError ? <LoadingState label="Loading proposal" /> : null}
            {proposal && draft ? (
                <div className="drawer-sections">
                    {error ? <div className="notice notice-error" role="alert">{error}</div> : null}
                    {copied && can.send ? <div className="notice notice-info" role="status">Copied. Paste it into Upwork, send it, then confirm below.</div> : null}
                    {proposal.quality_flags?.length ? (
                        <div className="notice notice-warning">
                            <strong>Check before sending</strong>
                            <ul className="flag-list">{proposal.quality_flags.map((flag) => <li key={flag}>{flag}</li>)}</ul>
                        </div>
                    ) : null}
                    {can.send && dirty ? <p className="field-hint">Save your changes before copying.</p> : null}

                    <JobFacts job={proposal.job} />
                    {proposal.job.latest_decision?.reason ? (
                        <section className="drawer-section">
                            <h3>Why it fits</h3>
                            <p>{proposal.job.latest_decision.reason}</p>
                        </section>
                    ) : null}

                    <section className="drawer-section">
                        <h3>Proposal</h3>
                        <dl className="job-facts">
                            <div><dt>Bid type</dt><dd>{statusLabel(options, 'upwork_job_types', proposal.bid_type)}</dd></div>
                            <div><dt>Send at</dt><dd>{formatDateTime(proposal.target_send_at, 'Any time')}</dd></div>
                            <div><dt>Expires</dt><dd>{formatDateTime(proposal.expires_at, 'No expiry')}</dd></div>
                            {proposal.sent_at ? <div><dt>Sent</dt><dd>{formatDateTime(proposal.sent_at)}{proposal.connects_spent ? ` · ${proposal.connects_spent} Connects` : ''}</dd></div> : null}
                            {proposal.approved_by ? <div><dt>Approved by</dt><dd>{proposal.approved_by.name}, {formatDateTime(proposal.approved_at)}</dd></div> : null}
                            {proposal.rejection_reason ? <div><dt>Rejected because</dt><dd>{proposal.rejection_reason}</dd></div> : null}
                        </dl>
                        <Input
                            icon={DollarSign}
                            type="number"
                            min="1"
                            step="0.01"
                            label={proposal.bid_type === 'hourly' ? 'Bid (per hour)' : 'Bid (fixed price)'}
                            value={draft.bid_amount}
                            disabled={!can.edit}
                            onChange={(event) => setDraft({ ...draft, bid_amount: event.target.value })}
                            error={errors.bid_amount?.[0]}
                        />
                        <Textarea
                            label="Cover letter"
                            rows={12}
                            value={draft.cover_letter}
                            disabled={!can.edit}
                            onChange={(event) => setDraft({ ...draft, cover_letter: event.target.value })}
                            hint={`${words} words (target 150 to 220)${proposal.edited ? ' · Edited' : ''}`}
                            error={errors.cover_letter?.[0]}
                        />
                        {draft.screening_answers.map((item, index) => (
                            <Textarea
                                key={item.question}
                                label={item.question}
                                rows={4}
                                value={item.answer}
                                disabled={!can.edit}
                                onChange={(event) => setDraft({
                                    ...draft,
                                    screening_answers: draft.screening_answers.map((answer, position) => (position === index ? { ...answer, answer: event.target.value } : answer)),
                                })}
                                error={errors[`screening_answers.${index}.answer`]?.[0] || (index === 0 ? errors.screening_answers?.[0] : undefined)}
                            />
                        ))}
                        {errors.status?.[0] ? <div className="field-error">{errors.status[0]}</div> : null}
                    </section>
                </div>
            ) : null}

            {rejecting ? (
                <Modal
                    title="Reject this proposal?"
                    description="The proposal and job are kept for tuning the rules; they just stop showing as open."
                    onClose={() => setRejecting(false)}
                    busy={busy === 'reject'}
                    role="alertdialog"
                    footer={(
                        <>
                            <Button variant="secondary" onClick={() => setRejecting(false)} disabled={busy === 'reject'}>Cancel</Button>
                            <Button variant="danger" onClick={reject} disabled={busy === 'reject'}>{busy === 'reject' ? 'Rejecting...' : 'Reject proposal'}</Button>
                        </>
                    )}
                >
                    <Textarea label="Reason (optional)" rows={3} value={rejectReason} onChange={(event) => setRejectReason(event.target.value)} error={errors.reason?.[0]} />
                </Modal>
            ) : null}

            {askSent ? (
                <Modal
                    title="Did you send it?"
                    description="Only confirm once the proposal has been submitted in Upwork."
                    onClose={() => setAskSent(false)}
                    busy={busy === 'sent'}
                    footer={(
                        <>
                            <Button variant="secondary" onClick={() => setAskSent(false)} disabled={busy === 'sent'}>Not yet</Button>
                            <Button icon={Check} onClick={markSent} disabled={busy === 'sent'}>{busy === 'sent' ? 'Saving...' : 'Yes, mark as sent'}</Button>
                        </>
                    )}
                >
                    <Input
                        type="number"
                        min="0"
                        label="Connects spent (optional)"
                        value={connects}
                        onChange={(event) => setConnects(event.target.value)}
                        error={errors.connects_spent?.[0]}
                    />
                </Modal>
            ) : null}
        </Drawer>
    );
}
