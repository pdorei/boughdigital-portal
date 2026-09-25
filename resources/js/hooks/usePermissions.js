import { useAuth } from '@/hooks/useAuth';

/**
 * Reads page-level abilities computed by the server's policies. These only
 * decide which controls to show; every request is authorized again server-side.
 */
export function usePermissions() {
    const { abilities } = useAuth();

    return {
        can: (ability) => Boolean(abilities[ability] ?? abilities['*']),
    };
}
