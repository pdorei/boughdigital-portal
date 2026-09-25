const KEY = 'bough:flash';

// Carries a success message across a full page navigation (for example after
// saving a form that redirects). Storage can be unavailable in private modes.
export function flash(message) {
    try {
        window.sessionStorage.setItem(KEY, message);
    } catch {
        // The redirect still happens; the user just misses the confirmation.
    }
}

export function consumeFlash() {
    try {
        const message = window.sessionStorage.getItem(KEY);
        window.sessionStorage.removeItem(KEY);

        return message;
    } catch {
        return null;
    }
}
