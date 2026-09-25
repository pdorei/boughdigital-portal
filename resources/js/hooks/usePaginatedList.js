import { useCallback, useEffect, useRef, useState } from 'react';

function readUrlFilters(keys) {
    const params = new URLSearchParams(window.location.search);

    return Object.fromEntries(keys.filter((key) => params.has(key)).map((key) => [key, params.get(key)]));
}

function writeUrlFilters(filters, page) {
    const params = new URLSearchParams();

    Object.entries({ ...filters, page: page > 1 ? page : '' }).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) params.set(key, value);
    });

    const query = params.toString();
    window.history.replaceState(null, '', `${window.location.pathname}${query ? `?${query}` : ''}`);
}

/**
 * Server-side filtered, paginated list. Filters (and the page) are mirrored in
 * the URL when syncUrl is set so a refresh or shared link keeps the view.
 */
export function usePaginatedList(fetcher, { initialFilters = {}, perPage = 15, syncUrl = false } = {}) {
    const fetcherRef = useRef(fetcher);
    fetcherRef.current = fetcher;

    const [filters, setFilters] = useState(() => ({
        ...initialFilters,
        ...(syncUrl ? readUrlFilters(Object.keys(initialFilters)) : {}),
    }));
    const [page, setPage] = useState(() => (syncUrl ? Number(new URLSearchParams(window.location.search).get('page')) || 1 : 1));
    const [rows, setRows] = useState([]);
    const [meta, setMeta] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [version, setVersion] = useState(0);

    useEffect(() => {
        let cancelled = false;

        const timeout = window.setTimeout(async () => {
            setLoading(true);
            setError(null);

            if (syncUrl) writeUrlFilters(filters, page);

            try {
                const response = await fetcherRef.current({ ...filters, page, per_page: perPage });

                if (!cancelled) {
                    setRows(response.data || []);
                    setMeta(response.meta || null);
                }
            } catch (apiError) {
                if (!cancelled) setError(apiError.message);
            } finally {
                if (!cancelled) setLoading(false);
            }
        }, 200);

        return () => {
            cancelled = true;
            window.clearTimeout(timeout);
        };
    }, [filters, page, perPage, syncUrl, version]);

    const setFilter = useCallback((name, value) => {
        setFilters((current) => ({ ...current, [name]: value }));
        setPage(1);
    }, []);

    const reload = useCallback(() => setVersion((current) => current + 1), []);

    const replaceRow = useCallback((updated) => {
        setRows((current) => current.map((row) => (row.id === updated.id ? updated : row)));
    }, []);

    return { rows, meta, loading, error, filters, setFilter, page, setPage, reload, replaceRow };
}
