import React from 'react';
import { Check } from 'lucide-react';

export default function StepProgress({ steps, current }) {
    return (
        <ol className="install-steps" aria-label="Installation steps">
            {steps.map((label, index) => {
                const state = index < current ? 'done' : index === current ? 'current' : 'todo';

                return (
                    <li key={label} className={`install-step is-${state}`} aria-current={index === current ? 'step' : undefined}>
                        <span className="install-step-dot">
                            {state === 'done' ? <Check size={14} strokeWidth={3} aria-hidden="true" /> : index + 1}
                        </span>
                        <span className="install-step-label">{label}</span>
                    </li>
                );
            })}
        </ol>
    );
}
