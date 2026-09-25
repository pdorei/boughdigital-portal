import { useWorkspace } from '@/hooks/useWorkspace';
import { formatDate, formatDateTime, formatTime } from '@/utils/date';

export function useDateFormatter() {
    const { datePreferences } = useWorkspace();

    return {
        datePreferences,
        formatDate: (value, fallback) => formatDate(value, datePreferences, fallback),
        formatDateTime: (value, fallback) => formatDateTime(value, datePreferences, fallback),
        formatTime: (value, fallback) => formatTime(value, datePreferences, fallback),
    };
}
