import React from 'react';

export default function IconButton({ icon: Icon, label, onClick, type = 'button' }) {
    return (
        <button className="icon-btn" type={type} onClick={onClick} title={label} aria-label={label}>
            <Icon size={18} />
        </button>
    );
}
