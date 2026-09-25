import React from 'react';

export default function ClientAccessSelector({ clients = [], selected = [], onChange }) {
    const selectedSet = new Set(selected.map((id) => Number(id)));

    function toggle(clientId) {
        const next = new Set(selectedSet);
        if (next.has(clientId)) {
            next.delete(clientId);
        } else {
            next.add(clientId);
        }
        onChange(Array.from(next));
    }

    return (
        <div className="list">
            {clients.map((client) => (
                <label className="list-row checkbox-row" key={client.id}>
                    <input type="checkbox" checked={selectedSet.has(client.id)} onChange={() => toggle(client.id)} />
                    <span>{client.name}</span>
                </label>
            ))}
            {!clients.length ? <div className="card-pad muted">No clients available for assignment.</div> : null}
        </div>
    );
}
