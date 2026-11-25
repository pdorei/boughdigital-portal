import React from 'react';
import { Link2, Plus, Tag, Trash2 } from 'lucide-react';
import FormError from '@/components/forms/FormError';
import Input from '@/components/forms/Input';
import Button from '@/components/ui/Button';
import IconButton from '@/components/ui/IconButton';

let nextKey = 0;

/**
 * Each row carries a local `key` so React keeps focus stable while rows are
 * added or removed; it is stripped before saving.
 */
export function withRowKeys(urls = []) {
    return urls.map((url) => ({ ...url, key: `url-${nextKey++}` }));
}

export default function KeyUrlList({ rows, onChange, max, errors = {} }) {
    function update(key, field, value) {
        onChange(rows.map((row) => (row.key === key ? { ...row, [field]: value } : row)));
    }

    return (
        <div className="field-label">
            <span>Key URLs</span>
            {rows.length ? (
                <div className="key-url-list">
                    {rows.map((row, index) => (
                        <div className="key-url-row" key={row.key}>
                            <Input icon={Tag} label="Label" value={row.label} onChange={(event) => update(row.key, 'label', event.target.value)} placeholder="Services" error={errors[`key_urls.${index}.label`]?.[0]} />
                            <Input icon={Link2} type="url" label="URL" value={row.url} onChange={(event) => update(row.key, 'url', event.target.value)} placeholder="https://example.com/services" error={errors[`key_urls.${index}.url`]?.[0]} />
                            {/* The empty label slot keeps the button level with the inputs, even when a field shows an error. */}
                            <div className="field-label">
                                <span aria-hidden="true">&nbsp;</span>
                                <IconButton icon={Trash2} label={`Remove ${row.label || 'URL'}`} onClick={() => onChange(rows.filter((item) => item.key !== row.key))} />
                            </div>
                        </div>
                    ))}
                </div>
            ) : <span className="field-hint">No key URLs yet. Add the pages AI tools should know about, such as the home, services or about page.</span>}
            <FormError message={errors.key_urls?.[0]} />
            <div>
                <Button icon={Plus} variant="secondary" onClick={() => onChange([...rows, ...withRowKeys([{ label: '', url: '' }])])} disabled={rows.length >= max}>
                    Add URL
                </Button>
            </div>
        </div>
    );
}
