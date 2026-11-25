import React from 'react';
import { KeyRound, Link2 } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';

/**
 * Providers the client can still be connected to. OAuth providers are listed
 * but marked as not available until their connection flow is built.
 */
export default function AvailableIntegrations({ providers, authTypes, onSelect }) {
    return (
        <div className="integration-grid">
            {providers.map((provider) => (
                <div className="card card-pad integration-card" key={provider.value}>
                    <div className="integration-card-head">
                        <strong>{provider.label}</strong>
                        {provider.available ? <Badge>Not connected</Badge> : <Badge status="draft">Not available yet</Badge>}
                    </div>
                    <span className="table-muted">
                        {authTypes.find((type) => type.value === provider.auth_type)?.label}
                    </span>
                    <div>
                        <Button icon={provider.auth_type === 'api_key' ? KeyRound : Link2} variant="secondary" onClick={() => onSelect(provider)}>
                            {provider.auth_type === 'api_key' ? 'Add API Key' : 'Connect'}
                        </Button>
                    </div>
                </div>
            ))}
        </div>
    );
}
