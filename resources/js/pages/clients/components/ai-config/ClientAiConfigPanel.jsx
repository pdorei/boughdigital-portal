import React, { useEffect, useState } from 'react';
import { RotateCcw, Save } from 'lucide-react';
import DisplayField from '@/components/forms/DisplayField';
import Textarea from '@/components/forms/Textarea';
import Button from '@/components/ui/Button';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import ErrorState from '@/components/ui/ErrorState';
import LoadingState from '@/components/ui/LoadingState';
import Toast from '@/components/ui/Toast';
import { getClientAiConfig, resetClientAiConfig, saveClientAiConfig } from '@/api/aiConfig';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { useWorkspace } from '@/hooks/useWorkspace';
import KeyUrlList, { withRowKeys } from '@/pages/clients/components/ai-config/KeyUrlList';

const TEXT_FIELDS = [
    { name: 'brand_tone', label: 'Brand tone', rows: 4, placeholder: 'Professional, friendly and authoritative. Avoid sounding overly corporate.' },
    { name: 'writing_preferences', label: 'Writing preferences', rows: 4, placeholder: 'Use Australian English. Prefer short paragraphs and clear headings.' },
    { name: 'industry_context', label: 'Industry context', rows: 4, placeholder: 'What the client does, who their customers are and where they operate.' },
];

const INSTRUCTIONS_FIELD = { name: 'custom_instructions', label: 'Custom instructions', rows: 8, placeholder: 'For example: Never make claims about guaranteed rankings.' };

export default function ClientAiConfigPanel({ client }) {
    const { workspaceSlug } = useWorkspace();
    const { formatDateTime } = useDateFormatter();
    const [config, setConfig] = useState(null);
    const [keyUrls, setKeyUrls] = useState([]);
    const [loadError, setLoadError] = useState(null);
    const [submitting, setSubmitting] = useState(false);
    const [confirmReset, setConfirmReset] = useState(false);
    const [errors, setErrors] = useState({});
    const [feedback, setFeedback] = useState({ message: null, tone: 'success' });

    function applyConfig(next) {
        setConfig(next);
        setKeyUrls(withRowKeys(next.key_urls));
    }

    useEffect(() => {
        getClientAiConfig(workspaceSlug, client.id)
            .then((response) => applyConfig(response.data))
            .catch((error) => setLoadError(error.message));
    }, [workspaceSlug, client.id]);

    async function handleSubmit(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});

        const form = new FormData(event.currentTarget);
        const payload = Object.fromEntries([...TEXT_FIELDS, INSTRUCTIONS_FIELD].map(({ name }) => [name, form.get(name) || null]));
        payload.key_urls = keyUrls
            .filter((row) => row.label.trim() || row.url.trim())
            .map(({ label, url }) => ({ label, url }));

        try {
            const response = await saveClientAiConfig(workspaceSlug, client.id, payload);
            applyConfig(response.data);
            setFeedback({ message: 'AI configuration saved.', tone: 'success' });
        } catch (error) {
            setErrors(error.errors);
            setFeedback({ message: error.message, tone: 'error' });
        } finally {
            setSubmitting(false);
        }
    }

    async function reset() {
        setSubmitting(true);

        try {
            const response = await resetClientAiConfig(workspaceSlug, client.id);
            applyConfig(response.data);
            setConfirmReset(false);
            setFeedback({ message: 'AI configuration reset.', tone: 'success' });
        } catch (error) {
            setFeedback({ message: error.message, tone: 'error' });
        } finally {
            setSubmitting(false);
        }
    }

    if (loadError) return <ErrorState title="Could not load AI configuration" copy={loadError} />;
    if (!config) return <LoadingState label="Loading AI configuration" />;

    const intro = (
        <p className="section-copy">
            These settings give future AI-powered tools context about {client.name}: how to write for them and what to know about their business. Every field is optional.
            {config.configured && config.updated_at ? ` Last updated ${formatDateTime(config.updated_at)}.` : ''}
        </p>
    );

    if (!config.can?.update) {
        return (
            <div className="form-stack">
                {intro}
                {[...TEXT_FIELDS, INSTRUCTIONS_FIELD].map(({ name, label }) => (
                    <DisplayField key={name} label={label} multiline>{config[name] || 'Not set.'}</DisplayField>
                ))}
                <DisplayField label="Key URLs" multiline>
                    {config.key_urls.length ? config.key_urls.map((entry) => (
                        <span key={entry.url} className="block"><strong>{entry.label}</strong>: <a className="link" href={entry.url} target="_blank" rel="noopener noreferrer">{entry.url}</a></span>
                    )) : 'Not set.'}
                </DisplayField>
            </div>
        );
    }

    return (
        <form key={config.updated_at || 'new'} className="form-stack" onSubmit={handleSubmit}>
            <Toast message={feedback.message} tone={feedback.tone} />
            {intro}
            {TEXT_FIELDS.map((field) => (
                <Textarea key={field.name} {...field} defaultValue={config[field.name] || ''} error={errors[field.name]?.[0]} />
            ))}
            <KeyUrlList rows={keyUrls} onChange={setKeyUrls} max={config.max_key_urls} errors={errors} />
            <Textarea {...INSTRUCTIONS_FIELD} defaultValue={config.custom_instructions || ''} error={errors.custom_instructions?.[0]} />
            <div className="inline-actions action-group-end">
                {config.configured && config.can?.reset ? (
                    <Button icon={RotateCcw} variant="secondary" onClick={() => setConfirmReset(true)} disabled={submitting}>Reset</Button>
                ) : null}
                <Button icon={Save} type="submit" disabled={submitting}>{submitting ? 'Saving...' : 'Save configuration'}</Button>
            </div>
            {confirmReset ? (
                <ConfirmDialog
                    title="Reset AI configuration?"
                    description={`All AI settings and key URLs for ${client.name} are cleared. This cannot be undone.`}
                    confirmLabel="Reset configuration"
                    loading={submitting}
                    onCancel={() => setConfirmReset(false)}
                    onConfirm={reset}
                />
            ) : null}
        </form>
    );
}
