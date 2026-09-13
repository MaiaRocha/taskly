/**
 * Converts a `<input type="datetime-local">` value (naive, no timezone —
 * interpreted by `Date` as the browser's local time) into an ISO-8601 string
 * with an explicit UTC offset, matching the backend's required `due_at` format.
 */
export function toApiDateTime(localValue: string): string {
    return new Date(localValue).toISOString();
}

/**
 * Converts an ISO-8601 UTC string from the API into the naive local value a
 * `<input type="datetime-local">` expects. Deliberately does NOT use
 * `apiIso.slice(0, 16)` or `toISOString().slice(0, 16)` — both stay in UTC,
 * which would silently shift the displayed time for any user not in UTC.
 * `getFullYear`/`getMonth`/`getDate`/`getHours`/`getMinutes` return the
 * components in the browser's local timezone, which is what the input needs.
 */
export function toDateTimeLocalValue(apiIso: string): string {
    const date = new Date(apiIso);
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** Formats an ISO-8601 UTC string from the API for display, in the browser's local timezone. */
export function formatTaskDueDate(apiIso: string): string {
    return new Date(apiIso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
}

const SHORT_MONTH_NAMES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

/**
 * Formats an ISO-8601 UTC timestamp as a short, relative-day label for the
 * Task Activity timeline — "Hoje, 15:42" / "Ontem, 18:20" / "13 set, 14:53"
 * (or "13 set 2025, 14:53" for a different year). Built manually instead of
 * `toLocaleDateString`'s pt-BR long form ("13 de set. de 2026") to keep the
 * compact style the timeline wants. Always in the browser's local timezone.
 */
export function formatActivityTimestamp(apiIso: string): string {
    const date = new Date(apiIso);
    const now = new Date();
    const time = date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

    const startOfDay = (value: Date) => new Date(value.getFullYear(), value.getMonth(), value.getDate()).getTime();
    const dayDiff = Math.round((startOfDay(now) - startOfDay(date)) / 86_400_000);

    if (dayDiff === 0) {
        return `Hoje, ${time}`;
    }

    if (dayDiff === 1) {
        return `Ontem, ${time}`;
    }

    const year = date.getFullYear() === now.getFullYear() ? '' : ` ${date.getFullYear()}`;

    return `${date.getDate()} ${SHORT_MONTH_NAMES[date.getMonth()]}${year}, ${time}`;
}
