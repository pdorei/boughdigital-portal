import React, { useEffect, useState } from 'react';
import { Clock, DollarSign, Gauge, Hash, Save } from 'lucide-react';
import Button from '@/components/ui/Button';
import Card from '@/components/ui/Card';
import ErrorState from '@/components/ui/ErrorState';
import LoadingState from '@/components/ui/LoadingState';
import Toast from '@/components/ui/Toast';
import Checkbox from '@/components/forms/Checkbox';
import FormError from '@/components/forms/FormError';
import Input from '@/components/forms/Input';
import TagInput from '@/components/forms/TagInput';
import Textarea from '@/components/forms/Textarea';
import { getUpworkSettings, updateUpworkSettings } from '@/api/platformSettings';
import { useWorkspace } from '@/hooks/useWorkspace';

const LEVELS = [
    { value: 'entry_level', label: 'Entry' },
    { value: 'intermediate', label: 'Intermediate' },
    { value: 'expert', label: 'Expert' },
];

const SEARCH_HELP = {
    a: 'Fixed-price jobs from this budget up (covers $1,000 to $4,999 and $5,000+).',
    b: 'Hourly jobs whose top rate is at least this amount. Jobs with no rate stated are kept.',
    c: 'Clients in these countries, any budget. Filtered in code, since the API does not filter by location.',
};

const PROMPTS = [
    { key: 'fit_check', label: 'Fit check', hint: 'Variables: {{track}}, {{job}}. Claude must answer with the JSON fit result.' },
    { key: 'cover_letter', label: 'Cover letter', hint: 'Variables: {{track}}, {{bid}}, {{fit_reason}}, {{job}}.' },
    { key: 'screening_answers', label: 'Screening answers', hint: 'Variables: {{job}}, {{questions}}.' },
];

// The sections this form owns. Other stored keys are kept by the server.
const SECTIONS = ['automation_enabled', 'run_interval_minutes', 'tracks', 'searches', 'fit', 'bidding', 'prompts'];

function numberOrNull(value) {
    return value === '' || value === null || value === undefined ? null : Number(value);
}

function NumberField({ label, value, onChange, error, icon = Hash, min = 0, step = 1, placeholder }) {
    return (
        <Input
            icon={icon}
            type="number"
            label={label}
            min={min}
            step={step}
            value={value ?? ''}
            placeholder={placeholder}
            onChange={(event) => onChange(event.target.value)}
            error={error}
        />
    );
}

export default function UpworkConfigurationForm() {
    const { options } = useWorkspace();
    const [form, setForm] = useState(null);
    const [loadError, setLoadError] = useState(null);
    const [errors, setErrors] = useState({});
    const [formError, setFormError] = useState(null);
    const [message, setMessage] = useState(null);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        getUpworkSettings().then((response) => setForm(response.data)).catch((apiError) => setLoadError(apiError.message));
    }, []);

    if (loadError) return <ErrorState title="Could not load Upwork settings" copy={loadError} />;
    if (!form) return <LoadingState label="Loading Upwork settings" />;

    const error = (key) => errors[key]?.[0];
    const update = (path, value) => {
        setForm((current) => {
            const next = structuredClone(current);
            const keys = path.split('.');
            let target = next;
            keys.slice(0, -1).forEach((key) => {
                target[key] = target[key] ?? {};
                target = target[key];
            });
            target[keys[keys.length - 1]] = value;

            return next;
        });
    };

    async function save(event) {
        event.preventDefault();
        setSaving(true);
        setErrors({});
        setFormError(null);
        setMessage(null);

        const payload = Object.fromEntries(SECTIONS.map((key) => [key, form[key]]));
        payload.run_interval_minutes = Number(payload.run_interval_minutes);
        payload.fit = Object.fromEntries(Object.entries(form.fit).map(([key, value]) => [key, Number(value)]));
        payload.bidding = Object.fromEntries(Object.entries(form.bidding).map(([key, value]) => [key, key === 'connects_balance' ? numberOrNull(value) : Number(value)]));
        payload.searches = Object.fromEntries(Object.entries(form.searches).map(([key, search]) => [key, {
            ...search,
            ...(search.fixed_budget_min !== undefined ? { fixed_budget_min: numberOrNull(search.fixed_budget_min) } : {}),
            ...(search.fixed_budget_max !== undefined ? { fixed_budget_max: numberOrNull(search.fixed_budget_max) } : {}),
            ...(search.hourly_rate_min !== undefined ? { hourly_rate_min: numberOrNull(search.hourly_rate_min) } : {}),
        }]));

        try {
            const response = await updateUpworkSettings(payload);
            setForm(response.data);
            setMessage(response.message || 'Upwork settings saved.');
        } catch (apiError) {
            setErrors(apiError.errors || {});
            setFormError(apiError.message);
            // Save sits at the bottom of a long form; bring the first problem into view.
            window.requestAnimationFrame(() => {
                document.querySelector('.has-error, .notice-error')?.scrollIntoView({ block: 'center', behavior: 'smooth' });
            });
        } finally {
            setSaving(false);
        }
    }

    const tracks = options.upwork_tracks || [];
    const searches = options.upwork_searches || [];

    return (
        <form className="form-stack" onSubmit={save} noValidate>
            <Toast message={message} />
            {formError ? <div className="notice notice-error" role="alert">{formError}</div> : null}

            <Card title="Automation">
                <div className="card-pad form-stack">
                    <Checkbox checked={Boolean(form.automation_enabled)} onChange={(event) => update('automation_enabled', event.target.checked)}>
                        Run searches automatically
                    </Checkbox>
                    <p className="field-hint">Runs need Upwork to be connected (<a className="strong-link" href="/admin/connections">Connections</a>). Proposals are always approved and sent by a person.</p>
                    <div className="field-grid">
                        <NumberField icon={Clock} label="Run every (minutes)" min={15} value={form.run_interval_minutes} onChange={(value) => update('run_interval_minutes', value)} error={error('run_interval_minutes')} />
                    </div>
                </div>
            </Card>

            <Card title="Searches">
                <div className="card-pad form-stack">
                    <p className="section-copy">Every keyword is searched each enabled way on every run, newest first. Results are de-duplicated by Upwork job ID.</p>
                    <div className="field-grid">
                        {tracks.map((track) => (
                            <TagInput
                                key={track.value}
                                label={`${track.label} keywords`}
                                values={form.tracks?.[track.value]?.keywords || []}
                                onChange={(values) => update(`tracks.${track.value}.keywords`, values)}
                                max={25}
                                hint="Press Enter after each keyword."
                                error={error(`tracks.${track.value}.keywords`) || Object.entries(errors).find(([key]) => key.startsWith(`tracks.${track.value}.keywords.`))?.[1]?.[0]}
                            />
                        ))}
                    </div>
                    <FormError message={error('searches') || error('tracks')} />
                    <div className="search-grid">
                        {searches.map((search) => {
                            const values = form.searches?.[search.value] || {};
                            const key = `searches.${search.value}`;

                            return (
                                <section key={search.value} className="form-section">
                                    <div>
                                        <h3>{search.label}</h3>
                                        <p>{SEARCH_HELP[search.value]}</p>
                                    </div>
                                    <Checkbox checked={Boolean(values.enabled)} onChange={(event) => update(`${key}.enabled`, event.target.checked)}>
                                        Enabled
                                    </Checkbox>
                                    {search.value === 'a' ? (
                                        <>
                                            <NumberField icon={DollarSign} label="Minimum budget" value={values.fixed_budget_min} onChange={(value) => update(`${key}.fixed_budget_min`, value)} error={error(`${key}.fixed_budget_min`)} />
                                            <NumberField icon={DollarSign} label="Maximum budget (optional)" value={values.fixed_budget_max} placeholder="No maximum" onChange={(value) => update(`${key}.fixed_budget_max`, value)} error={error(`${key}.fixed_budget_max`)} />
                                        </>
                                    ) : null}
                                    {search.value === 'b' ? (
                                        <NumberField icon={DollarSign} label="Minimum hourly rate" value={values.hourly_rate_min} onChange={(value) => update(`${key}.hourly_rate_min`, value)} error={error(`${key}.hourly_rate_min`)} />
                                    ) : null}
                                    {search.value === 'c' ? (
                                        <TagInput
                                            label="Client countries"
                                            values={values.client_countries || []}
                                            onChange={(list) => update(`${key}.client_countries`, list)}
                                            max={30}
                                            error={error(`${key}.client_countries`)}
                                        />
                                    ) : null}
                                    <fieldset className="checkbox-group">
                                        <legend>Experience levels</legend>
                                        {LEVELS.map((level) => {
                                            const selected = values.experience_levels || [];

                                            return (
                                                <Checkbox
                                                    key={level.value}
                                                    checked={selected.includes(level.value)}
                                                    onChange={(event) => update(`${key}.experience_levels`, event.target.checked ? [...selected, level.value] : selected.filter((item) => item !== level.value))}
                                                >
                                                    {level.label}
                                                </Checkbox>
                                            );
                                        })}
                                    </fieldset>
                                </section>
                            );
                        })}
                    </div>
                </div>
            </Card>

            <Card title="Fit rules">
                <div className="card-pad form-stack">
                    <p className="section-copy">Hard rules run in code before Claude. The score cut-offs decide accept, review or skip.</p>
                    <div className="field-grid">
                        <NumberField icon={DollarSign} label="Minimum hourly rate (top of range)" value={form.fit.min_hourly_rate} onChange={(value) => update('fit.min_hourly_rate', value)} error={error('fit.min_hourly_rate')} />
                        <NumberField icon={DollarSign} label="Minimum fixed budget" value={form.fit.min_fixed_budget} onChange={(value) => update('fit.min_fixed_budget', value)} error={error('fit.min_fixed_budget')} />
                        <NumberField label="Maximum proposals already sent" min={1} value={form.fit.max_proposals} onChange={(value) => update('fit.max_proposals', value)} error={error('fit.max_proposals')} />
                        <NumberField icon={Clock} label="Maximum job age (minutes)" min={15} value={form.fit.max_job_age_minutes} onChange={(value) => update('fit.max_job_age_minutes', value)} error={error('fit.max_job_age_minutes')} />
                        <NumberField icon={Gauge} label="Auto-accept score (and above)" min={1} value={form.fit.auto_accept_score} onChange={(value) => update('fit.auto_accept_score', value)} error={error('fit.auto_accept_score')} />
                        <NumberField icon={Gauge} label="Review score (and above)" value={form.fit.review_score} onChange={(value) => update('fit.review_score', value)} error={error('fit.review_score')} />
                    </div>
                </div>
            </Card>

            <Card title="Bidding and Connects">
                <div className="card-pad form-stack">
                    <p className="section-copy">
                        Hourly: top rate below the minimum is skipped; below the premium threshold (or no rate) bids the standard rate; otherwise the premium rate.
                        Fixed: at or above the minimum budget bids the client&apos;s budget; no budget goes to review.
                    </p>
                    <div className="field-grid">
                        <NumberField icon={DollarSign} label="Standard hourly bid" min={1} value={form.bidding.hourly_bid_standard} onChange={(value) => update('bidding.hourly_bid_standard', value)} error={error('bidding.hourly_bid_standard')} />
                        <NumberField icon={DollarSign} label="Premium hourly bid (and threshold)" min={1} value={form.bidding.hourly_bid_premium} onChange={(value) => update('bidding.hourly_bid_premium', value)} error={error('bidding.hourly_bid_premium')} />
                        <NumberField label="Daily Connects cap" value={form.bidding.daily_connects_cap} onChange={(value) => update('bidding.daily_connects_cap', value)} error={error('bidding.daily_connects_cap')} />
                        <NumberField label="Current Connects balance (optional)" value={form.bidding.connects_balance} placeholder="Not tracked" onChange={(value) => update('bidding.connects_balance', value)} error={error('bidding.connects_balance')} />
                        <NumberField label="Balance floor" value={form.bidding.connects_balance_floor} onChange={(value) => update('bidding.connects_balance_floor', value)} error={error('bidding.connects_balance_floor')} />
                        <NumberField icon={Clock} label="Send no earlier than (minutes after posting)" value={form.bidding.send_delay_min_minutes} onChange={(value) => update('bidding.send_delay_min_minutes', value)} error={error('bidding.send_delay_min_minutes')} />
                        <NumberField icon={Clock} label="Send no later than (minutes after posting)" value={form.bidding.send_delay_max_minutes} onChange={(value) => update('bidding.send_delay_max_minutes', value)} error={error('bidding.send_delay_max_minutes')} />
                        <NumberField icon={Clock} label="Proposal expires (minutes after posting)" min={30} value={form.bidding.proposal_expiry_minutes} onChange={(value) => update('bidding.proposal_expiry_minutes', value)} error={error('bidding.proposal_expiry_minutes')} />
                    </div>
                    <p className="field-hint">The balance goes down by the Connects entered when a proposal is marked as sent. Drafting stops when the daily cap is reached or the balance is below the floor.</p>
                </div>
            </Card>

            <Card title="Prompts">
                <div className="card-pad form-stack">
                    {PROMPTS.map((prompt) => (
                        <Textarea
                            key={prompt.key}
                            label={prompt.label}
                            rows={10}
                            value={form.prompts?.[prompt.key] || ''}
                            onChange={(event) => update(`prompts.${prompt.key}`, event.target.value)}
                            hint={prompt.hint}
                            error={error(`prompts.${prompt.key}`)}
                        />
                    ))}
                </div>
            </Card>

            <div className="inline-actions action-group-end sticky-actions">
                <Button type="submit" icon={Save} disabled={saving}>{saving ? 'Saving...' : 'Save Upwork settings'}</Button>
            </div>
        </form>
    );
}
