import React, { useCallback, useEffect, useState } from 'react';
import { KeyRound, PlugZap, Save, Unplug } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import Card from '@/components/ui/Card';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import ErrorState from '@/components/ui/ErrorState';
import LoadingState from '@/components/ui/LoadingState';
import Toast from '@/components/ui/Toast';
import DisplayField from '@/components/forms/DisplayField';
import Input from '@/components/forms/Input';
import PasswordInput from '@/components/forms/PasswordInput';
import Select from '@/components/forms/Select';
import { disconnectConnection, getConnections, UPWORK_CONNECT_URL, updateClaudeConnection } from '@/api/platformSettings';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { formatNumber } from '@/utils/formatNumber';

function money(value) {
    return `$${Number(value || 0).toFixed(2)}`;
}

function UpworkCard({ connection, onDisconnect }) {
    const { formatDateTime } = useDateFormatter();
    const fixtures = connection.driver === 'fake';

    return (
        <Card
            title="Upwork"
            action={<Badge status={connection.connected ? 'connected' : connection.status}>{connection.connected ? 'Connected' : connection.status}</Badge>}
        >
            <div className="card-pad form-stack">
                <p className="section-copy">
                    Reads new jobs through Upwork&apos;s official GraphQL API with OAuth. Proposals are never submitted automatically.
                </p>
                {fixtures ? (
                    <div className="notice notice-info">
                        Fixture mode (UPWORK_DRIVER=fake): jobs come from local test data and no Upwork API calls are made.
                    </div>
                ) : null}
                {!connection.app_configured ? (
                    <div className="notice notice-warning">Set UPWORK_CLIENT_ID and UPWORK_CLIENT_SECRET in the environment before connecting.</div>
                ) : null}
                {connection.last_error ? <div className="notice notice-warning">{connection.last_error}</div> : null}
                <div className="field-grid">
                    <DisplayField label="Account">{connection.account_name || 'Not connected'}</DisplayField>
                    <DisplayField label="Status">{connection.connected ? 'Connected' : 'Not connected'}</DisplayField>
                    <DisplayField label="Connected">{formatDateTime(connection.connected_at, 'Never')}</DisplayField>
                    <DisplayField label="Last token refresh">{formatDateTime(connection.last_refreshed_at, 'Never')}</DisplayField>
                </div>
                <div className="inline-actions">
                    <Button icon={PlugZap} href={UPWORK_CONNECT_URL} variant={connection.connected ? 'secondary' : 'primary'} aria-disabled={!connection.app_configured}>
                        {connection.connected ? 'Reconnect' : 'Connect Upwork'}
                    </Button>
                    {connection.connected ? <Button icon={Unplug} variant="danger" onClick={onDisconnect}>Disconnect</Button> : null}
                </div>
            </div>
        </Card>
    );
}

function ClaudeCard({ connection, onSaved, onDisconnect }) {
    const [form, setForm] = useState({
        api_key: '',
        model: connection.model,
        fit_model: connection.fit_model,
        monthly_cap_usd: String(connection.monthly_cap_usd ?? ''),
    });
    const [errors, setErrors] = useState({});
    const [error, setError] = useState(null);
    const [saving, setSaving] = useState(false);
    const usage = connection.usage_this_month || {};
    const modelOptions = connection.models || [];

    async function save(event) {
        event.preventDefault();
        setSaving(true);
        setErrors({});
        setError(null);

        const payload = { model: form.model, fit_model: form.fit_model, monthly_cap_usd: Number(form.monthly_cap_usd) };
        if (form.api_key.trim()) payload.api_key = form.api_key.trim();

        try {
            const response = await updateClaudeConnection(payload);
            setForm((current) => ({ ...current, api_key: '' }));
            onSaved(response.data, response.message);
        } catch (apiError) {
            setErrors(apiError.errors || {});
            setError(apiError.message);
        } finally {
            setSaving(false);
        }
    }

    const set = (field) => (event) => setForm((current) => ({ ...current, [field]: event.target.value }));

    return (
        <Card
            title="Claude (Anthropic)"
            action={<Badge status={connection.credential_configured ? 'connected' : 'disconnected'}>{connection.credential_configured ? 'Configured ✓' : 'Not configured'}</Badge>}
        >
            <form className="card-pad form-stack" onSubmit={save}>
                <p className="section-copy">Scores job fit and drafts cover letters. The key is stored encrypted and is never shown again.</p>
                {error ? <div className="notice notice-error" role="alert">{error}</div> : null}
                <PasswordInput
                    name="api_key"
                    label="API key"
                    value={form.api_key}
                    onChange={set('api_key')}
                    autoComplete="off"
                    placeholder={connection.credential_configured ? 'Configured ✓  Enter a new key to replace it' : 'sk-ant-...'}
                    error={errors.api_key?.[0]}
                />
                <div className="field-grid">
                    <Select label="Proposal model" value={form.model} onChange={set('model')} options={modelOptions} error={errors.model?.[0]} />
                    <Select label="Fit check model" value={form.fit_model} onChange={set('fit_model')} options={modelOptions} error={errors.fit_model?.[0]} />
                    <Input icon={KeyRound} type="number" min="0" step="1" label="Monthly cap (USD)" value={form.monthly_cap_usd} onChange={set('monthly_cap_usd')} error={errors.monthly_cap_usd?.[0]} />
                    <DisplayField label="Used this month (estimate)">
                        {`${money(usage.cost_usd)} of ${money(connection.monthly_cap_usd)} · ${formatNumber(usage.input_tokens || 0)} in / ${formatNumber(usage.output_tokens || 0)} out tokens`}
                    </DisplayField>
                </div>
                <div className="inline-actions">
                    <Button type="submit" icon={Save} disabled={saving}>{saving ? 'Saving...' : 'Save Claude settings'}</Button>
                    {connection.credential_configured ? <Button icon={Unplug} variant="danger" onClick={onDisconnect} disabled={saving}>Remove key</Button> : null}
                </div>
            </form>
        </Card>
    );
}

export default function ConnectionsSettings() {
    const [connections, setConnections] = useState(null);
    const [error, setError] = useState(null);
    const [message, setMessage] = useState(null);
    const [confirm, setConfirm] = useState(null);
    const [busy, setBusy] = useState(false);

    const load = useCallback(async () => {
        try {
            setConnections((await getConnections()).data);
        } catch (apiError) {
            setError(apiError.message);
        }
    }, []);

    useEffect(() => {
        load();
    }, [load]);

    async function disconnect() {
        setBusy(true);
        try {
            const response = await disconnectConnection(confirm);
            setConnections(response.data);
            setMessage(response.message);
            setConfirm(null);
        } catch (apiError) {
            setError(apiError.message);
        } finally {
            setBusy(false);
        }
    }

    if (error && !connections) return <ErrorState title="Could not load connections" copy={error} />;
    if (!connections) return <LoadingState label="Loading connections" />;

    return (
        <div className="form-stack">
            <Toast message={message} />
            {error ? <Toast message={error} tone="error" /> : null}
            <div className="grid-two">
                <UpworkCard connection={connections.upwork} onDisconnect={() => setConfirm('upwork')} />
                <ClaudeCard
                    key={connections.anthropic.credential_configured ? 'configured' : 'empty'}
                    connection={connections.anthropic}
                    onSaved={(data, savedMessage) => {
                        setConnections(data);
                        setMessage(savedMessage);
                    }}
                    onDisconnect={() => setConfirm('anthropic')}
                />
            </div>
            {confirm ? (
                <ConfirmDialog
                    title={confirm === 'upwork' ? 'Disconnect Upwork?' : 'Remove the Claude API key?'}
                    description={confirm === 'upwork'
                        ? 'Stored tokens are deleted and automatic runs stop until Upwork is connected again. Jobs and proposals are kept.'
                        : 'Fit checks and drafts stop until a new key is saved. Usage history is kept.'}
                    confirmLabel={confirm === 'upwork' ? 'Disconnect' : 'Remove key'}
                    loading={busy}
                    onCancel={() => setConfirm(null)}
                    onConfirm={disconnect}
                />
            ) : null}
        </div>
    );
}
