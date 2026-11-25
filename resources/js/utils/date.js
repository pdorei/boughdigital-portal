const MONTH_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const MONTH_LONG = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const WEEKDAY_LONG = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

function preferenceValue(preferences, key, fallback) {
    const value = preferences?.[key];

    if (value && typeof value === 'object') return value.value || fallback;

    return value || fallback;
}

function pad(value) {
    return String(value).padStart(2, '0');
}

function dateOnlyParts(value) {
    const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})$/);

    if (!match) return null;

    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    const weekday = new Date(Date.UTC(year, month - 1, day)).getUTCDay();

    return { year, month, day, weekday, hour24: 0, minute: 0 };
}

function zonedParts(value, timezone) {
    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) return null;

    const parts = new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        hour: '2-digit',
        hour12: false,
        minute: '2-digit',
        month: '2-digit',
        timeZone: timezone,
        weekday: 'long',
        year: 'numeric',
    }).formatToParts(date).reduce((carry, part) => ({ ...carry, [part.type]: part.value }), {});

    return {
        year: Number(parts.year),
        month: Number(parts.month),
        day: Number(parts.day),
        weekdayName: parts.weekday,
        hour24: Number(parts.hour === '24' ? '0' : parts.hour),
        minute: Number(parts.minute),
    };
}

function renderPattern(parts, pattern) {
    if (!parts) return '';

    const hour12 = parts.hour24 % 12 || 12;
    const replacements = {
        Y: String(parts.year),
        m: pad(parts.month),
        d: pad(parts.day),
        M: MONTH_SHORT[parts.month - 1],
        F: MONTH_LONG[parts.month - 1],
        l: parts.weekdayName || WEEKDAY_LONG[parts.weekday],
        H: pad(parts.hour24),
        h: pad(hour12),
        i: pad(parts.minute),
        A: parts.hour24 >= 12 ? 'PM' : 'AM',
    };

    return pattern.replace(/Y|m|d|M|F|l|H|h|i|A/g, (token) => replacements[token] ?? token);
}

export function effectiveDatePreferences(preferences = {}) {
    return {
        timezone: preferenceValue(preferences, 'timezone', 'Australia/Sydney'),
        dateFormat: preferenceValue(preferences, 'date_format', 'd/m/Y'),
        dateTimeFormat: preferenceValue(preferences, 'datetime_format', 'd/m/Y H:i'),
    };
}

export function formatDate(value, preferences = {}, fallback = 'No date') {
    if (!value) return fallback;

    const effective = effectiveDatePreferences(preferences);
    const parts = dateOnlyParts(value) || zonedParts(value, effective.timezone);
    const formatted = renderPattern(parts, effective.dateFormat);

    return formatted || fallback;
}

export function formatDateTime(value, preferences = {}, fallback = 'Not available') {
    if (!value) return fallback;

    const effective = effectiveDatePreferences(preferences);
    const formatted = renderPattern(zonedParts(value, effective.timezone), effective.dateTimeFormat);

    return formatted || fallback;
}

export function formatTime(value, preferences = {}, fallback = 'Not available') {
    if (!value) return fallback;

    const effective = effectiveDatePreferences(preferences);
    const formatted = renderPattern(zonedParts(value, effective.timezone), 'H:i');

    return formatted || fallback;
}

function dateOnly(value) {
    const parts = dateOnlyParts(value);

    return parts ? new Date(Date.UTC(Number(parts.year), Number(parts.month) - 1, Number(parts.day))) : null;
}

/**
 * Month heading for a date-only value such as a publish date: "September 2026".
 */
export function formatMonthYear(value, fallback = 'No date') {
    const date = dateOnly(value);

    return date ? date.toLocaleDateString('en-GB', { month: 'long', year: 'numeric', timeZone: 'UTC' }) : fallback;
}

/**
 * Short day label for a date-only value: "28 Sep".
 */
export function formatDayMonth(value, fallback = '—') {
    const date = dateOnly(value);

    // Built from parts because en-GB/en-AU abbreviate September as "Sept".
    return date ? `${date.getUTCDate()} ${date.toLocaleDateString('en-US', { month: 'short', timeZone: 'UTC' })}` : fallback;
}
