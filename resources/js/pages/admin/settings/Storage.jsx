import React, { useMemo, useState } from 'react';
import { Database, Save, TestTube2 } from 'lucide-react';
import Button from '@/components/ui/Button';
import Card from '@/components/ui/Card';
import ErrorState from '@/components/ui/ErrorState';
import Toast from '@/components/ui/Toast';
import Checkbox from '@/components/forms/Checkbox';
import Input from '@/components/forms/Input';
import Select from '@/components/forms/Select';
import { testStorageConnection, updateStorageSettings } from '@/api/storageSettings';
import { useWorkspace } from '@/hooks/useWorkspace';

export default function StorageSettingsSection() {
    const { pageData } = useWorkspace();
    const initial = pageData.storage_settings || { driver: 'local', s3: {} };
    const [driver, setDriver] = useState(initial.driver || 'local');
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState(null);
    const [formError, setFormError] = useState(null);
    const [submitting, setSubmitting] = useState(false);
    const [testing, setTesting] = useState(false);

    const s3 = useMemo(() => initial.s3 || {}, [initial]);

    async function handleSubmit(event) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);

        setSubmitting(true);
        setErrors({});
        setFormError(null);
        setMessage(null);

        try {
            await updateStorageSettings({
                driver,
                s3: {
                    access_key_id: form.get('access_key_id') || '',
                    secret_access_key: form.get('secret_access_key') || '',
                    region: form.get('region') || '',
                    bucket: form.get('bucket') || '',
                    endpoint: form.get('endpoint') || '',
                    url: form.get('url') || '',
                    use_path_style_endpoint: form.has('use_path_style_endpoint'),
                },
            });
            setMessage('Storage settings saved.');
        } catch (apiError) {
            setErrors(apiError.errors || {});
            setFormError(apiError.message);
        } finally {
            setSubmitting(false);
        }
    }

    async function handleTest() {
        setTesting(true);
        setFormError(null);
        setMessage(null);

        try {
            const response = await testStorageConnection(driver);
            setMessage(response.message || 'Connection successful.');
        } catch (apiError) {
            setFormError(apiError.message);
        } finally {
            setTesting(false);
        }
    }

    return (
        <div className="form-stack">
            <Toast message={message} />
            {formError ? <ErrorState title="Storage settings error" copy={formError} /> : null}
            <form className="form-stack" onSubmit={handleSubmit}>
                <Card title="Storage Driver" padded>
                    <div className="form-grid">
                        <Select
                            name="driver"
                            label="Storage Driver"
                            value={driver}
                            onChange={(event) => setDriver(event.target.value)}
                            options={[
                                { value: 'local', label: 'Local' },
                                { value: 's3', label: 'S3' },
                            ]}
                            error={errors.driver?.[0]}
                        />
                    </div>
                    <p className="section-copy">Changing storage affects new uploads. Existing files are not migrated automatically.</p>
                </Card>

                {driver === 's3' ? (
                    <Card title="S3 Configuration" padded>
                        <div className="form-grid">
                            <Input icon={Database} name="access_key_id" label="Access Key ID" defaultValue="" autoComplete="off" error={errors['s3.access_key_id']?.[0]} />
                            <Input icon={Database} name="secret_access_key" type="password" label="Secret Access Key" defaultValue="" autoComplete="new-password" error={errors['s3.secret_access_key']?.[0]} />
                            <Input icon={Database} name="region" label="Region" defaultValue={s3.region || ''} error={errors['s3.region']?.[0]} />
                            <Input icon={Database} name="bucket" label="Bucket" defaultValue={s3.bucket || ''} error={errors['s3.bucket']?.[0]} />
                            <Input icon={Database} name="endpoint" label="Endpoint" defaultValue={s3.endpoint || ''} error={errors['s3.endpoint']?.[0]} />
                            <Input icon={Database} name="url" label="URL" defaultValue={s3.url || ''} error={errors['s3.url']?.[0]} />
                        </div>
                        <Checkbox name="use_path_style_endpoint" defaultChecked={Boolean(s3.use_path_style_endpoint)}>Use path-style endpoint</Checkbox>
                        <p className="section-copy">Saved keys are encrypted and never displayed back in plaintext. Current saved values: {s3.access_key_id || 'No access key saved'} / {s3.secret_access_key || 'No secret saved'}.</p>
                    </Card>
                ) : null}

                <div className="inline-actions">
                    <Button icon={Save} type="submit" disabled={submitting}>{submitting ? 'Saving...' : 'Save Storage Settings'}</Button>
                    <Button icon={TestTube2} variant="secondary" type="button" disabled={testing} onClick={handleTest}>{testing ? 'Testing...' : 'Test Connection'}</Button>
                </div>
            </form>
        </div>
    );
}
