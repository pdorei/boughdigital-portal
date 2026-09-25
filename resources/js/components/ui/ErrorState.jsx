import React from 'react';
import { AlertCircle } from 'lucide-react';

export default function ErrorState({ title = 'Something went wrong', copy = 'Please try again.' }) {
    return (
        <div className="empty">
            <div>
                <AlertCircle size={30} />
                <h3 className="font-bold">{title}</h3>
                <p className="section-copy">{copy}</p>
            </div>
        </div>
    );
}
