export function labelize(value) {
    return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

export function slugify(value) {
    return String(value || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
}
