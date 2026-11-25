const UNITS = ['B', 'KB', 'MB', 'GB'];

export function formatBytes(bytes) {
    if (!bytes) return '—';

    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), UNITS.length - 1);
    const value = bytes / 1024 ** index;

    return `${value.toFixed(index === 0 ? 0 : 1)} ${UNITS[index]}`;
}

export function formatNumber(value) {
    return Number(value).toLocaleString('en-AU');
}
