import React from 'react';

export default function FormError({ message }) {
    if (!message) {
        return null;
    }

    return <span className="field-error">{message}</span>;
}
