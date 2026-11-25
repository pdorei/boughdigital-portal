import React, { useEffect, useRef, useState } from 'react';
import { Database, Globe, Hash, Lock, LogIn, Mail, RotateCw, Server, User, UserRound } from 'lucide-react';
import Input from '@/components/forms/Input';
import PasswordInput from '@/components/forms/PasswordInput';
import Button from '@/components/ui/Button';
import { runInstall, testDatabase } from '@/api/installer';
import InstallLayout from './components/InstallLayout';
import RequirementsList from './components/RequirementsList';
import StatusNotice from './components/StatusNotice';
import StepProgress from './components/StepProgress';

const STEPS = ['Welcome', 'Portal', 'Database', 'Admin', 'Done'];
const STEP_FOR_FIELD = { app_: 1, db_: 2, admin_: 3 };
const ENVIRONMENTS = [
    { value: 'production', title: 'Live site', description: 'Errors are hidden from visitors.' },
    { value: 'local', title: 'Development', description: 'Shows detailed errors. Not for a live site.' },
];

/**
 * First-run setup wizard. Each step is its own <form> so the browser's own
 * validation runs before moving on; the server re-validates everything on install.
 * Steps stay mounted (hidden) so going Back keeps what was typed.
 */
export default function Installer({ config }) {
    const defaults = config.defaults || {};
    const [step, setStep] = useState(0);
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState(null);
    const [dbStatus, setDbStatus] = useState(null);
    const [result, setResult] = useState(null);
    const [form, setForm] = useState({
        app_name: defaults.appName || 'Bough Digital Portal',
        app_url: defaults.appUrl || '',
        app_env: 'production',
        db_host: '127.0.0.1',
        db_port: String(defaults.dbPort || 5432),
        db_database: '',
        db_username: '',
        db_password: '',
        admin_name: '',
        admin_email: '',
    });
    const rootRef = useRef(null);
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
        rootRef.current?.querySelector(`[data-step="${step}"] input:not([type=radio]), [data-step="${step}"] button, [data-step="${step}"] a`)?.focus();
    }, [step]);

    const field = (name) => ({
        name,
        value: form[name],
        onChange: (event) => setForm((current) => ({ ...current, [name]: event.target.value })),
        error: errors[name]?.[0],
    });

    const goTo = (next) => setStep(next);

    const next = (event) => {
        event?.preventDefault();
        setErrors({});
        setMessage(null);
        goTo(step + 1);
    };

    const databaseFields = () => {
        const { db_host, db_port, db_database, db_username, db_password } = form;
        return { db_host, db_port, db_database, db_username, db_password };
    };

    const checkDatabase = async () => {
        setBusy(true);
        setDbStatus(null);
        setErrors({});

        const { ok, data } = await testDatabase(config.routes.database, databaseFields());

        setBusy(false);
        setDbStatus({ ok, message: data.message });
        if (!ok) {
            setErrors(data.errors || {});
        }

        return ok;
    };

    const continueFromDatabase = async (event) => {
        event.preventDefault();
        if (busy) {
            return;
        }
        if (await checkDatabase()) {
            next();
        }
    };

    const install = async (event) => {
        event.preventDefault();
        if (busy) {
            return;
        }

        const passwords = new FormData(event.currentTarget);

        setBusy(true);
        setErrors({});
        setMessage(null);

        const { ok, data } = await runInstall(config.routes.install, {
            ...form,
            admin_password: passwords.get('admin_password') || '',
            admin_password_confirmation: passwords.get('admin_password_confirmation') || '',
        });

        setBusy(false);

        if (ok) {
            setResult(data);
            goTo(4);
            return;
        }

        const fieldErrors = data.errors || {};
        setErrors(fieldErrors);
        setMessage(data.message);

        // Jump back to the first step that has a problem.
        const first = Object.keys(fieldErrors)[0] || '';
        const prefix = Object.keys(STEP_FOR_FIELD).find((p) => first.startsWith(p));
        if (prefix) {
            goTo(STEP_FOR_FIELD[prefix]);
        }
        if (first === 'db_connection') {
            setDbStatus({ ok: false, message: data.message });
            goTo(2);
        }
    };

    const versions = config.versions || {};

    return (
        <InstallLayout footer={`Laravel ${versions.laravel || ''} · PHP ${versions.php || ''}`}>
            <div className="auth-card-panel install-panel" ref={rootRef}>
                <StepProgress steps={STEPS} current={step} />

                {/* 1. Welcome */}
                <section data-step="0" hidden={step !== 0}>
                    <h1>Welcome</h1>
                    <p>Let's get the Bough Digital Portal running. You'll need your PostgreSQL database details, then you'll name the portal and create the platform Super Admin.</p>

                    <RequirementsList requirements={config.requirements || []} passed={Boolean(config.meetsRequirements)} />

                    <div className="install-actions is-end">
                        {config.meetsRequirements
                            ? <Button onClick={() => next()}>Let's go</Button>
                            : <Button variant="secondary" icon={RotateCw} href="/install">Check again</Button>}
                    </div>
                </section>

                {/* 2. Portal */}
                <form data-step="1" hidden={step !== 1} className="form-stack" onSubmit={next}>
                    <div>
                        <h1>Your portal</h1>
                        <p>You can change these later in the .env file.</p>
                    </div>

                    <Input icon={UserRound} label="Portal name" required maxLength={60} {...field('app_name')} />
                    <div>
                        <Input icon={Globe} label="Portal address (URL)" type="url" required {...field('app_url')} />
                        <p className="field-hint install-hint">The address people use to reach the portal, without a trailing slash.</p>
                    </div>

                    <fieldset className="install-choice">
                        <legend>Environment</legend>
                        <div className="install-choice-grid">
                            {ENVIRONMENTS.map((env) => (
                                <label key={env.value} className="install-choice-option">
                                    <input
                                        type="radio"
                                        name="app_env"
                                        value={env.value}
                                        checked={form.app_env === env.value}
                                        onChange={() => setForm((current) => ({ ...current, app_env: env.value }))}
                                    />
                                    <span>
                                        <strong>{env.title}</strong>
                                        <small>{env.description}</small>
                                    </span>
                                </label>
                            ))}
                        </div>
                    </fieldset>

                    <div className="install-actions">
                        <Button variant="secondary" onClick={() => goTo(0)}>Back</Button>
                        <Button type="submit">Continue</Button>
                    </div>
                </form>

                {/* 3. Database */}
                <form data-step="2" hidden={step !== 2} className="form-stack" onSubmit={continueFromDatabase}>
                    <div>
                        <h1>Database</h1>
                        <p>PostgreSQL only. The database must already exist; the tables are created for you.</p>
                    </div>

                    <div className="install-row">
                        <Input icon={Server} label="Host" required autoComplete="off" {...field('db_host')} />
                        <Input icon={Hash} label="Port" type="number" min={1} max={65535} required {...field('db_port')} />
                    </div>
                    <Input icon={Database} label="Database name" required autoComplete="off" {...field('db_database')} />
                    <div className="install-row is-even">
                        <Input icon={User} label="Username" required autoComplete="off" {...field('db_username')} />
                        <Input icon={Lock} label="Password" type="password" autoComplete="new-password" {...field('db_password')} />
                    </div>

                    <StatusNotice ok={dbStatus?.ok} message={dbStatus?.message} />

                    <div className="install-actions">
                        <Button variant="secondary" onClick={() => goTo(1)} disabled={busy}>Back</Button>
                        <div className="inline-actions">
                            <Button
                                variant="secondary"
                                disabled={busy}
                                onClick={(event) => event.currentTarget.form.reportValidity() && checkDatabase()}
                            >
                                Test connection
                            </Button>
                            <Button type="submit" disabled={busy}>{busy ? 'Checking…' : 'Continue'}</Button>
                        </div>
                    </div>
                </form>

                {/* 4. Admin */}
                <form data-step="3" hidden={step !== 3} className="form-stack" onSubmit={install}>
                    <div>
                        <h1>Super Admin</h1>
                        <p>The platform account you'll sign in with to create workspaces and users.</p>
                    </div>

                    <Input icon={UserRound} label="Your name" required autoComplete="name" {...field('admin_name')} />
                    <Input icon={Mail} label="Email" type="email" required autoComplete="email" {...field('admin_email')} />
                    <PasswordInput name="admin_password" label="Password" autoComplete="new-password" required error={errors.admin_password?.[0]} />
                    <PasswordInput name="admin_password_confirmation" label="Confirm password" autoComplete="new-password" required error={errors.admin_password_confirmation?.[0]} />
                    <p className="field-hint install-hint">At least 8 characters.</p>

                    <StatusNotice ok={false} message={message} />

                    <div className="install-actions">
                        <Button variant="secondary" onClick={() => goTo(2)} disabled={busy}>Back</Button>
                        <Button type="submit" disabled={busy} aria-busy={busy}>{busy ? 'Installing…' : 'Install portal'}</Button>
                    </div>
                </form>

                {/* 5. Done */}
                <section data-step="4" hidden={step !== 4} className="install-done">
                    <h1>You're all set</h1>
                    <p>{form.app_name} is installed. The settings were saved to <code>.env</code> and this installer is now switched off.</p>

                    <dl className="install-summary">
                        <div><dt>Portal</dt><dd>{form.app_url}</dd></div>
                        <div><dt>Database</dt><dd>{form.db_database}</dd></div>
                        <div><dt>Super Admin</dt><dd>{form.admin_email}</dd></div>
                        <div><dt>Cache &amp; queue</dt><dd>{result?.cache === 'redis' ? 'Redis' : 'Database (no Redis)'}</dd></div>
                    </dl>

                    <Button icon={LogIn} href={result?.login || '/login'} full>Sign in</Button>
                </section>
            </div>
        </InstallLayout>
    );
}
