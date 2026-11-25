import { slugify } from '@/utils/text';

/**
 * Keeps the selected tab in the query string (?tab=content&section=articles)
 * so links, refreshes and "back" land on the same tab.
 */
export function tabFromUrl(param, tabs, fallback) {
    const value = new URLSearchParams(window.location.search).get(param);

    return tabs.find((tab) => slugify(tab) === value) || fallback;
}

export function writeTabToUrl(param, tab) {
    const url = new URL(window.location.href);
    url.searchParams.set(param, slugify(tab));
    window.history.replaceState(null, '', url);
}
