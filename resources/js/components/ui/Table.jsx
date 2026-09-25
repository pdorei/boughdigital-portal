import React from 'react';

export default function Table({ columns, rows, rowKey }) {
    return (
        <div className="table-wrap">
            <table className="data-table">
                <thead>
                    <tr>
                        {columns.map((column) => <th key={column.key} className={column.key === 'actions' ? 'table-actions-heading' : undefined}>{column.label}</th>)}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={rowKey(row)}>
                            {columns.map((column) => (
                                <td key={column.key} className={[column.key === 'actions' ? 'table-actions-cell' : null, column.cellClassName].filter(Boolean).join(' ') || undefined}>
                                    {column.render ? column.render(row) : row[column.key]}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
