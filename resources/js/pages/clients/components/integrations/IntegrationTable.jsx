import React from 'react';
import { Pencil, Trash2, Unplug } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import RowActions from '@/components/ui/RowActions';
import Table from '@/components/ui/Table';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { useWorkspace } from '@/hooks/useWorkspace';
import { optionLabel } from '@/utils/options';

export default function IntegrationTable({ integrations, onEdit, onDisconnect, onDelete }) {
    const { formatDateTime } = useDateFormatter();
    const { options } = useWorkspace();

    return (
        <Table
            columns={[
                {
                    key: 'name',
                    label: 'Integration',
                    render: (integration) => (
                        <span className="grid">
                            <strong>{integration.display_name}</strong>
                            {integration.name ? <span className="table-muted">{integration.provider_label}</span> : null}
                        </span>
                    ),
                },
                { key: 'auth', label: 'Authentication', render: (integration) => integration.auth_type_label },
                {
                    key: 'status',
                    label: 'Status',
                    render: (integration) => <Badge status={integration.status}>{optionLabel(options.integration_statuses, integration.status)}</Badge>,
                },
                {
                    key: 'credential',
                    label: 'Credential',
                    render: (integration) => (integration.credential_configured
                        ? `${integration.auth_type_label} configured`
                        : <span className="table-muted">Not configured</span>),
                },
                { key: 'connected_at', label: 'Connected', render: (integration) => formatDateTime(integration.connected_at, '—') },
                {
                    key: 'actions',
                    label: 'Actions',
                    render: (integration) => (
                        <RowActions
                            label={`Open actions for ${integration.display_name}`}
                            actions={[
                                integration.can?.update ? { label: 'Edit', icon: Pencil, onClick: () => onEdit(integration) } : null,
                                integration.can?.update && integration.credential_configured ? {
                                    label: 'Disconnect',
                                    icon: Unplug,
                                    confirm: {
                                        title: `Disconnect ${integration.display_name}?`,
                                        description: 'The saved credential is removed. The integration stays listed so it can be reconnected later.',
                                        confirmLabel: 'Disconnect',
                                    },
                                    onClick: () => onDisconnect(integration),
                                } : null,
                                integration.can?.delete ? {
                                    label: 'Delete',
                                    icon: Trash2,
                                    variant: 'danger',
                                    confirm: {
                                        title: `Delete ${integration.display_name}?`,
                                        description: 'The integration and its saved credential are removed from this client permanently.',
                                        confirmLabel: 'Delete integration',
                                    },
                                    onClick: () => onDelete(integration),
                                } : null,
                            ]}
                        />
                    ),
                },
            ]}
            rows={integrations}
            rowKey={(integration) => integration.id}
        />
    );
}
