import React from 'react';
import { CalendarDays, Clock3, Save } from 'lucide-react';
import Select from '@/components/forms/Select';
import PageHeader from '@/components/layout/PageHeader';
import Button from '@/components/ui/Button';
import Card from '@/components/ui/Card';
import Toast from '@/components/ui/Toast';
import { useAuth } from '@/hooks/useAuth';
import { usePermissions } from '@/hooks/usePermissions';
import { useWorkspace } from '@/hooks/useWorkspace';

export default function SettingsIndex({ base }) {
    const { csrf, errors, old, status } = useAuth();
    const { can } = usePermissions();
    const {
        datePreferenceOptions,
        workspaceBusinessDatePreferences,
        workspaceDatePreferences,
        workspaceName,
    } = useWorkspace();
    const canManageWorkspace = can('settings.update');

    return (
        <div className="page-stack">
            <PageHeader eyebrow={workspaceName || 'Workspace'} title="Settings" description="Workspace defaults for time zone and date formats. Members can override them for themselves in their profile." />
            <div className="profile-grid">
                <Card title="Date & Time" padded>
                    <Toast message={status} />
                    <form className="form-stack" method="post" action={`${base}/settings/date-time`}>
                        <input type="hidden" name="_token" value={csrf} />
                        <Select
                            name="timezone"
                            label="Timezone"
                            defaultValue={old.timezone ?? workspaceDatePreferences.timezone ?? ''}
                            options={[
                                { value: '', label: `Use Application Default (${workspaceBusinessDatePreferences.timezone || 'Australia/Sydney'})` },
                                ...(datePreferenceOptions.timezone_options || []),
                            ]}
                            error={errors.timezone?.[0]}
                            disabled={!canManageWorkspace}
                        />
                        <Select
                            name="date_format"
                            label="Date Format"
                            defaultValue={old.date_format ?? workspaceDatePreferences.date_format ?? ''}
                            options={[
                                { value: '', label: `Use Application Default (${workspaceBusinessDatePreferences.date_format || 'd/m/Y'})` },
                                ...(datePreferenceOptions.date_format_options || []),
                            ]}
                            error={errors.date_format?.[0]}
                            disabled={!canManageWorkspace}
                        />
                        <Select
                            name="datetime_format"
                            label="Date/Time Format"
                            defaultValue={old.datetime_format ?? workspaceDatePreferences.datetime_format ?? ''}
                            options={[
                                { value: '', label: `Use Application Default (${workspaceBusinessDatePreferences.datetime_format || 'd/m/Y H:i'})` },
                                ...(datePreferenceOptions.datetime_format_options || []),
                            ]}
                            error={errors.datetime_format?.[0]}
                            disabled={!canManageWorkspace}
                        />
                        {canManageWorkspace
                            ? <Button icon={Save} type="submit">Save workspace settings</Button>
                            : <p className="muted text-sm">Only workspace admins can change these settings.</p>}
                    </form>
                </Card>
                <Card title="Effective Workspace Time" padded>
                    <div className="system-preview">
                        <div className="preview-row">
                            <Clock3 size={19} />
                            <div>
                                <span>Timezone</span>
                                <strong>{workspaceBusinessDatePreferences.timezone || 'Australia/Sydney'}</strong>
                            </div>
                        </div>
                        <div className="preview-row">
                            <CalendarDays size={19} />
                            <div>
                                <span>Formats</span>
                                <strong>{workspaceBusinessDatePreferences.date_format || 'd/m/Y'} / {workspaceBusinessDatePreferences.datetime_format || 'd/m/Y H:i'}</strong>
                            </div>
                        </div>
                    </div>
                </Card>
            </div>
        </div>
    );
}
