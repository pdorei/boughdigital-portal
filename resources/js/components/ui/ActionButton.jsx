import React from 'react';

export default function ActionButton({
    children,
    className = '',
    href = null,
    icon: Icon,
    label,
    onClick,
    type = 'button',
    variant = 'default',
    ...props
}) {
    const text = label || children;
    const classes = `action-btn action-btn-${variant}${className ? ` ${className}` : ''}`;
    const content = (
        <>
            {Icon ? <Icon size={15} aria-hidden="true" /> : null}
            <span>{text}</span>
        </>
    );
    const sharedProps = {
        'aria-label': typeof text === 'string' ? text : undefined,
        className: classes,
        title: typeof text === 'string' ? text : undefined,
        ...props,
    };

    if (href) {
        return <a href={href} {...sharedProps}>{content}</a>;
    }

    return <button type={type} onClick={onClick} {...sharedProps}>{content}</button>;
}
