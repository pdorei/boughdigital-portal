import React from 'react';
import { statusTone } from '@/utils/status';
import { labelize } from '@/utils/text';

export default function Badge({ children, status = 'default' }) {
    const tone = statusTone(status);
    const content = typeof children === 'string' || typeof children === 'number' ? labelize(children) : children;

    return <span className={`badge${tone !== 'default' ? ` badge-${tone}` : ''}`}>{content}</span>;
}
