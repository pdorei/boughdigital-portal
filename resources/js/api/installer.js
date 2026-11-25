/**
 * The installer API runs outside the web middleware group (no session, so no
 * CSRF token), which is why it doesn't use the shared portal API client.
 * Always resolves to { ok, status, data } so the wizard can show 422 field
 * errors, 500 failures and network problems.
 */
async function post(url, body) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(body),
        });
        const data = await response.json().catch(() => ({ message: `The server returned an error (${response.status}).` }));

        return { ok: response.ok, status: response.status, data };
    } catch {
        return { ok: false, status: 0, data: { message: 'Could not reach the server. Check your connection and try again.' } };
    }
}

export function testDatabase(url, fields) {
    return post(url, fields);
}

export function runInstall(url, fields) {
    return post(url, fields);
}
