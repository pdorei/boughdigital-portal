import React from 'react';
import { CalendarDays, KeyRound, Mail, Save, User } from 'lucide-react';
import Input from '@/components/forms/Input';
import PasswordInput from '@/components/forms/PasswordInput';
import Select from '@/components/forms/Select';
import Button from '@/components/ui/Button';
import Card from '@/components/ui/Card';
import Toast from '@/components/ui/Toast';
import { useAuth } from '@/hooks/useAuth';
import { useWorkspace } from '@/hooks/useWorkspace';
import { optionLabel } from '@/utils/options';

export default function Profile({ base }) {
    const { csrf, errors, old, status, user } = useAuth();
    const { datePreferenceOptions, datePreferences, options, userDatePreferences, workspaceName } = useWorkspace();

    return (
        <div className="profile-grid">
            <Card title="Profile" padded>
                <p className="section-copy">{workspaceName || 'Bough Digital'} / {optionLabel(options.roles, user?.role)}</p>
                <Toast message={status} />
                <form className="form-stack" method="post" action={`${base}/profile`}>
                    <input type="hidden" name="_token" value={csrf} />
                    <Input icon={User} name="name" label="Name" defaultValue={old.name || user?.name || ''} autoComplete="name" error={errors.name?.[0]} />
                    <Input icon={Mail} name="email" type="email" label="Email" defaultValue={old.email || user?.email || ''} autoComplete="email" error={errors.email?.[0]} />
                    <Input icon={User} name="avatar_url" label="Avatar URL" defaultValue={old.avatar_url || user?.avatar_url || ''} error={errors.avatar_url?.[0]} />
                    <Button icon={Save} type="submit">Save profile</Button>
                </form>
            </Card>
            <Card title="Date & Time" padded>
                <form className="form-stack" method="post" action={`${base}/profile/date-time`}>
                    <input type="hidden" name="_token" value={csrf} />
                    <Select
                        name="timezone"
                        label="Timezone"
                        defaultValue={old.timezone ?? userDatePreferences.timezone ?? ''}
                        options={datePreferenceOptions.user_timezone_options || []}
                        error={errors.timezone?.[0]}
                    />
                    <Select
                        name="date_format"
                        label="Date Format"
                        defaultValue={old.date_format ?? userDatePreferences.date_format ?? ''}
                        options={datePreferenceOptions.user_date_format_options || []}
                        error={errors.date_format?.[0]}
                    />
                    <Select
                        name="datetime_format"
                        label="Date/Time Format"
                        defaultValue={old.datetime_format ?? userDatePreferences.datetime_format ?? ''}
                        options={datePreferenceOptions.user_datetime_format_options || []}
                        error={errors.datetime_format?.[0]}
                    />
                    <div className="preview-row">
                        <CalendarDays size={19} />
                        <div>
                            <span>Effective</span>
                            <strong>{datePreferences.timezone?.value || 'Australia/Sydney'} / {datePreferences.date_format?.value || 'd/m/Y'} / {datePreferences.datetime_format?.value || 'd/m/Y H:i'}</strong>
                        </div>
                    </div>
                    <Button icon={Save} type="submit">Save date preferences</Button>
                </form>
            </Card>
            <Card title="Change Password" padded>
                <form id="password" className="form-stack" method="post" action={`${base}/password`}>
                    <input type="hidden" name="_token" value={csrf} />
                    <PasswordInput name="current_password" label="Current password" error={errors.current_password?.[0]} />
                    <PasswordInput name="password" label="New password" autoComplete="new-password" error={errors.password?.[0]} />
                    <PasswordInput name="password_confirmation" label="Confirm new password" autoComplete="new-password" error={errors.password_confirmation?.[0]} />
                    <Button icon={KeyRound} type="submit">Update password</Button>
                </form>
            </Card>
        </div>
    );
}
