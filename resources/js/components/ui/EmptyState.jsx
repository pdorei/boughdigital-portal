import React from 'react';
import { Sparkles } from 'lucide-react';

export default function EmptyState({ icon: Icon = Sparkles, title, copy }) {
    return (
        <div className="empty">
            <div>
                <Icon size={30} />
                <h3 className="font-bold">{title}</h3>
                <p className="section-copy">{copy}</p>
            </div>
        </div>
    );
}
