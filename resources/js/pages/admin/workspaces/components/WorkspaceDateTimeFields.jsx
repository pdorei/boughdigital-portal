import React, { useState } from 'react';
import { Clock3 } from 'lucide-react';
import Select from '@/components/forms/Select';

export default function WorkspaceDateTimeFields({ settings, options, errors }) {
    const [values, setValues] = useState({
        timezone: settings.timezone || '',
        date_format: settings.date_format || '',
        datetime_format: settings.datetime_format || '',
    });

    function updateValue(key) {
        return (event) => setValues((current) => ({ ...current, [key]: event.target.value }));
    }

    return (
        <div className="form-section">
            <div>
                <h3>Date & Time</h3>
                <p>Structured controls for the workspace preferences stored in settings.</p>
            </div>
            <Select
                name="timezone"
                label="Timezone"
                value={values.timezone}
                onChange={updateValue('timezone')}
                options={[
                    { value: '', label: 'Use Application Default (Australia/Sydney)' },
                    ...(options.timezone_options || []),
                ]}
                error={errors['settings.timezone']?.[0] || errors.timezone?.[0]}
            />
            <div className="form-row">
                <Select
                    name="date_format"
                    label="Date Format"
                    value={values.date_format}
                    onChange={updateValue('date_format')}
                    options={[
                        { value: '', label: 'Use Application Default (19/08/2026)' },
                        ...(options.date_format_options || []),
                    ]}
                    error={errors['settings.date_format']?.[0] || errors.date_format?.[0]}
                />
                <Select
                    name="datetime_format"
                    label="Date/Time Format"
                    value={values.datetime_format}
                    onChange={updateValue('datetime_format')}
                    options={[
                        { value: '', label: 'Use Application Default (19/08/2026 14:30)' },
                        ...(options.datetime_format_options || []),
                    ]}
                    error={errors['settings.datetime_format']?.[0] || errors.datetime_format?.[0]}
                />
            </div>
            <div className="preview-row">
                <Clock3 size={19} />
                <div>
                    <span>Selected</span>
                    <strong>{values.timezone || 'Australia/Sydney'} / {values.date_format || 'd/m/Y'} / {values.datetime_format || 'd/m/Y H:i'}</strong>
                </div>
            </div>
        </div>
    );
}
