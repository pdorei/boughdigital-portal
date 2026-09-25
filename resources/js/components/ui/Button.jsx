import React from 'react';

export default function Button({ children, icon: Icon, variant = 'primary', full = false, type = 'button', href = null, className = '', ...props }) {
    const classes = `btn btn-${variant}${full ? ' btn-full' : ''}${className ? ` ${className}` : ''}`;
    const content = (
        <>
            {Icon ? <Icon size={17} /> : null}
            {children}
        </>
    );

    if (href) {
        return <a className={classes} href={href} {...props}>{content}</a>;
    }

    return <button className={classes} type={type} {...props}>{content}</button>;
}
