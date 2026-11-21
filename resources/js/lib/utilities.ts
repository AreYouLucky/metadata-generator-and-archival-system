import axios, { isAxiosError } from 'axios';

/**
 * Shared axios instance for TanStack Query hooks. Laravel sets the XSRF-TOKEN
 * cookie and axios echoes it back as X-XSRF-TOKEN on same-origin requests.
 */
export const http = axios.create({
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    withCredentials: true,
});

/**
 * A readable message from a failed request: Laravel's `message`, the first
 * validation error, or a generic fallback.
 */
export function getErrorMessage(
    error: unknown,
    fallback = 'Something went wrong. Please try again.',
): string {
    if (
        isAxiosError<{ message?: string; errors?: Record<string, string[]> }>(
            error,
        )
    ) {
        const data = error.response?.data;
        const firstValidationError = data?.errors
            ? Object.values(data.errors)[0]?.[0]
            : undefined;

        return firstValidationError ?? data?.message ?? fallback;
    }

    return fallback;
}

/**
 * A position in a video, e.g. 75.4 -> "1:15", 3725 -> "1:02:05".
 */
export function formatTimestamp(seconds: number): string {
    const total = Math.max(0, Math.floor(seconds));
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const secs = String(total % 60).padStart(2, '0');

    return hours > 0
        ? `${hours}:${String(minutes).padStart(2, '0')}:${secs}`
        : `${minutes}:${secs}`;
}

/**
 * Editor-style clock with tenths, e.g. 83.46 -> "00:01:23.4".
 */
export function formatClock(seconds: number): string {
    const safe = Math.max(0, seconds);
    const hours = Math.floor(safe / 3600);
    const minutes = Math.floor((safe % 3600) / 60);
    const secs = Math.floor(safe % 60);
    const tenths = Math.floor((safe * 10) % 10);

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}.${tenths}`;
}

/**
 * YouTube chapter lines: "00:00 Intro" (first chapter must be 00:00).
 */
export function formatChapterLines(
    chapters: { start: number; title: string }[],
): string {
    const useHours = chapters.some((chapter) => chapter.start >= 3600);

    return chapters
        .map((chapter) => {
            const total = Math.max(0, Math.floor(chapter.start));
            const hours = Math.floor(total / 3600);
            const minutes = String(Math.floor((total % 3600) / 60)).padStart(
                2,
                '0',
            );
            const seconds = String(total % 60).padStart(2, '0');
            const stamp = useHours
                ? `${hours}:${minutes}:${seconds}`
                : `${minutes}:${seconds}`;

            return `${stamp} ${chapter.title}`;
        })
        .join('\n');
}

/**
 * Human-readable size, e.g. 1536 -> "1.5 KB".
 */
export function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const units = ['KB', 'MB', 'GB', 'TB'];
    let value = bytes / 1024;
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }

    return `${value.toFixed(value >= 10 ? 0 : 1)} ${units[unit]}`;
}

/**
 * Local date, e.g. "21 Nov 2026".
 */
export function formatDate(value: string | null): string {
    return value
        ? new Date(value).toLocaleDateString([], {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : '—';
}

/**
 * A one- or multi-day event, e.g. "10–12 Jul 2026", "30 Jun – 2 Jul 2026".
 */
export function formatDateRange(
    start: string | null,
    end: string | null,
): string {
    if (!start) {
        return '—';
    }

    if (!end || end === start) {
        return formatDate(start);
    }

    const from = new Date(start);
    const to = new Date(end);
    const sameYear = from.getFullYear() === to.getFullYear();
    const sameMonth = sameYear && from.getMonth() === to.getMonth();

    if (sameMonth) {
        return `${from.getDate()}–${formatDate(end)}`;
    }

    const head = from.toLocaleDateString([], {
        day: 'numeric',
        month: 'short',
        ...(sameYear ? {} : { year: 'numeric' }),
    });

    return `${head} – ${formatDate(end)}`;
}

/**
 * Index of the segment playing at `time` (binary search over sorted starts),
 * or -1 before the first segment.
 */
export function findSegmentIndex(
    segments: { start: number; end: number }[],
    time: number,
): number {
    let low = 0;
    let high = segments.length - 1;
    let found = -1;

    while (low <= high) {
        const middle = (low + high) >> 1;

        if (segments[middle].start <= time) {
            found = middle;
            low = middle + 1;
        } else {
            high = middle - 1;
        }
    }

    return found;
}

/**
 * Venue text and the picked place, e.g. "Capitol grounds, Capitol Site, Cebu City, Cebu".
 */
export function formatPlace(
    venue: string | null,
    place: string | null,
): string | null {
    const parts = [venue, place].filter((part): part is string => !!part);

    return parts.length > 0 ? parts.join(', ') : null;
}

/**
 * Local time with seconds, e.g. "14:03:27".
 */
export function formatClockTime(value: string | Date): string {
    return new Date(value).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

/**
 * A snake_case value as words, e.g. "checksum_verified" → "Checksum verified".
 */
export function formatEnumLabel(value: string): string {
    const words = value.replace(/_/g, ' ');

    return words.charAt(0).toUpperCase() + words.slice(1);
}

/**
 * Local date and time, e.g. "21 Nov 2026, 14:03".
 */
export function formatDateTime(value: string | null): string {
    return value
        ? new Date(value).toLocaleString([], {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          })
        : '—';
}
