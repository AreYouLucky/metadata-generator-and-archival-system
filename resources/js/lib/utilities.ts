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
 * A typed position back to seconds: "1:15" -> 75, "1:02:05.5" -> 3725.5,
 * "90" -> 90. Null when it isn't a time.
 */
export function parseTimestamp(text: string): number | null {
    const parts = text.trim().split(':');

    if (
        parts.length > 3 ||
        parts.some((part) => !/^\d+(\.\d+)?$/.test(part)) ||
        parts.slice(0, -1).some((part) => part.includes('.'))
    ) {
        return null;
    }

    return parts.reduce((total, part) => total * 60 + Number(part), 0);
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

/** Chip colours for speakers, by position in the record's speaker list. */
const SPEAKER_COLORS = [
    'bg-yellow-400/25 text-yellow-800 ring-yellow-500/50',
    'bg-amber-500/15 text-amber-700 ring-amber-500/40 dark:bg-amber-500/20 dark:text-amber-200 dark:ring-amber-400/40',
    'bg-emerald-500/15 text-emerald-700 ring-emerald-500/40 dark:bg-emerald-500/20 dark:text-emerald-200 dark:ring-emerald-400/40',
    'bg-fuchsia-500/15 text-fuchsia-700 ring-fuchsia-500/40 dark:bg-fuchsia-500/20 dark:text-fuchsia-200 dark:ring-fuchsia-400/40',
    'bg-rose-500/15 text-rose-700 ring-rose-500/40 dark:bg-rose-500/20 dark:text-rose-200 dark:ring-rose-400/40',
    'bg-lime-500/15 text-lime-700 ring-lime-500/40 dark:bg-lime-500/20 dark:text-lime-200 dark:ring-lime-400/40',
    'bg-violet-500/15 text-violet-700 ring-violet-500/40 dark:bg-violet-500/20 dark:text-violet-200 dark:ring-violet-400/40',
    'bg-orange-500/15 text-orange-700 ring-orange-500/40 dark:bg-orange-500/20 dark:text-orange-200 dark:ring-orange-400/40',
];

export function speakerColor(position: number): string {
    return SPEAKER_COLORS[Math.max(0, position) % SPEAKER_COLORS.length];
}

/** The name an encoder gave a speaker, or its label (S1, S2…). */
export function speakerName(speaker: {
    label: string;
    display_name: string | null;
}): string {
    return speaker.display_name ?? speaker.label;
}

/**
 * Whether a key press belongs to a text field, menu or dialog control rather
 * than to the page (so page shortcuts must leave it alone).
 */
export function isTypingTarget(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    return (
        target.isContentEditable ||
        target.closest(
            'input, textarea, select, [role="combobox"], [role="listbox"], [role="menu"], [role="dialog"], [role="slider"]',
        ) !== null
    );
}

const languageNames =
    typeof Intl !== 'undefined' && 'DisplayNames' in Intl
        ? new Intl.DisplayNames(['en'], { type: 'language' })
        : null;

/**
 * An ISO 639 / BCP 47 code as a name, e.g. "fil" -> "Filipino", "tl" ->
 * "Tagalog"; the code itself when the browser doesn't know it.
 */
export function languageName(code: string | null): string {
    if (!code || code === 'und') {
        return 'Unknown language';
    }

    try {
        return languageNames?.of(code) ?? code;
    } catch {
        return code;
    }
}

/**
 * A transcript line to paste elsewhere: "[1:02] Host: Welcome." (the
 * timestamp only when asked).
 */
export function formatLineForCopy(
    line: { start: number; text: string },
    speaker: string | null,
    withTimestamp: boolean,
): string {
    return `${withTimestamp ? `[${formatTimestamp(line.start)}] ` : ''}${speaker ? `${speaker}: ` : ''}${line.text}`;
}

/** "30000/1001" or "25" -> frames per second, or null. */
export function parseFrameRate(rate: string | null): number | null {
    if (!rate) {
        return null;
    }

    const [numerator, denominator = '1'] = rate.split('/');
    const value = Number(numerator) / Number(denominator);

    return Number.isFinite(value) && value > 0 ? value : null;
}

/** 29.97 and 59.94 fps count timecode in drop-frame (";" before the frames). */
function isDropFrame(rate: number): boolean {
    return Math.abs(rate - 29.97) < 0.01 || Math.abs(rate - 59.94) < 0.01;
}

/** A SMPTE timecode ("01:00:00:00" or ";" for drop-frame) as a frame count. */
function timecodeToFrames(timecode: string, rate: number): number {
    const match = /^(\d{2}):(\d{2}):(\d{2})[:;.](\d{2,3})$/.exec(timecode);

    if (!match) {
        return 0;
    }

    const [hours, minutes, seconds, frames] = match.slice(1).map(Number);
    const nominal = Math.round(rate);
    const total = (hours * 3600 + minutes * 60 + seconds) * nominal + frames;

    if (!isDropFrame(rate)) {
        return total;
    }

    const drop = Math.round(rate * 0.0666666);
    const totalMinutes = hours * 60 + minutes;

    return total - drop * (totalMinutes - Math.floor(totalMinutes / 10));
}

/**
 * A position as SMPTE timecode (PROJECT_SPEC §11): HH:MM:SS:FF from the
 * master's start timecode and frame rate, with ";" and dropped frame numbers
 * at 29.97 and 59.94 fps. Falls back to 25 fps when the rate is unknown.
 */
export function formatTimecode(
    seconds: number,
    frameRate: string | null,
    startTimecode: string | null,
): string {
    const rate = parseFrameRate(frameRate) ?? 25;
    const nominal = Math.round(rate);
    let frames =
        Math.floor(Math.max(0, seconds) * rate + 1e-6) +
        (startTimecode ? timecodeToFrames(startTimecode, rate) : 0);
    const drop = isDropFrame(rate);

    if (drop) {
        const dropped = Math.round(rate * 0.0666666);
        const perTenMinutes = Math.round(rate * 600);
        const perMinute = nominal * 60 - dropped;
        const tens = Math.floor(frames / perTenMinutes);
        const rest = frames % perTenMinutes;
        frames +=
            9 * dropped * tens +
            (rest > dropped
                ? dropped * Math.floor((rest - dropped) / perMinute)
                : 0);
    }

    const pad = (value: number) => String(value).padStart(2, '0');
    const ff = frames % nominal;
    const totalSeconds = Math.floor(frames / nominal);

    return `${pad(Math.floor(totalSeconds / 3600) % 24)}:${pad(Math.floor(totalSeconds / 60) % 60)}:${pad(totalSeconds % 60)}${drop ? ';' : ':'}${pad(ff)}`;
}

/**
 * For a shadcn <SelectTrigger> in a narrow column: fill the column and cut a
 * long value with an ellipsis instead of spilling over the next field (the
 * shipped trigger is `w-fit` and its value span is a flex box, which can't
 * truncate).
 */
export const SELECT_FILL_TRUNCATE =
    'w-full min-w-0 *:data-[slot=select-value]:block! *:data-[slot=select-value]:truncate';

/**
 * A whole or decimal number with thousands separators, e.g. 12345.6 → "12,345.6".
 */
export function formatNumber(value: number, maximumFractionDigits = 0): string {
    return value.toLocaleString([], { maximumFractionDigits });
}

/**
 * A total running time in words, e.g. 5400 → "1 h 30 min", 300 → "5 min".
 */
export function formatRunningTime(seconds: number): string {
    const totalMinutes = Math.round(Math.max(0, seconds) / 60);
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;

    if (hours === 0) {
        return `${minutes} min`;
    }

    return minutes === 0
        ? `${formatNumber(hours)} h`
        : `${formatNumber(hours)} h ${minutes} min`;
}

/**
 * An estimated cost in US dollars, e.g. 0.4213 → "$0.42"; tiny amounts keep
 * more digits so they don't read as zero.
 */
export function formatCost(value: number): string {
    return value.toLocaleString([], {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: value > 0 && value < 0.01 ? 4 : 2,
    });
}

/** A piece of chat text, and the video position it names if it is a time. */
export type TimestampPart = { text: string; seconds: number | null };

/** "1:05", "12:30" or "1:02:05", not part of a longer number or a clock time ("10:30 AM"). */
const TIMESTAMP_PATTERN =
    /(?<![\d:.])(?:\d{1,2}:)?\d{1,2}:[0-5]\d(?![\d:])(?!\s?[AaPp]\.?[Mm]\b)/g;

/**
 * Splits text at the video positions it mentions, e.g. "Opens (0:42)." →
 * ["Opens (", "0:42" (42 s), ")."], so the times can be clickable.
 */
export function splitTimestamps(text: string): TimestampPart[] {
    const parts: TimestampPart[] = [];
    let last = 0;

    for (const match of text.matchAll(TIMESTAMP_PATTERN)) {
        const index = match.index ?? 0;

        if (index > last) {
            parts.push({ text: text.slice(last, index), seconds: null });
        }

        parts.push({ text: match[0], seconds: parseTimestamp(match[0]) });
        last = index + match[0].length;
    }

    if (last < text.length) {
        parts.push({ text: text.slice(last), seconds: null });
    }

    return parts;
}

/**
 * The cited moment a time in an answer belongs to: the citation it falls in
 * (give or take a couple of seconds), else the only record cited, else none.
 */
export function findCitationAt<
    T extends { record_id: string; start: number; end: number },
>(seconds: number, citations: T[]): T | null {
    const covering = citations.find(
        (citation) =>
            seconds >= citation.start - 2 && seconds <= citation.end + 2,
    );

    if (covering) {
        return covering;
    }

    const records = new Set(citations.map((citation) => citation.record_id));

    return records.size === 1 ? citations[0] : null;
}

/**
 * An app link as a path on this site with `?t=` set to the whole second,
 * e.g. to open a video there. Links the server stored carry APP_URL's host,
 * which may differ from the one in the address bar (localhost vs 127.0.0.1),
 * and Inertia cannot visit another origin, so only the path is kept.
 */
export function withStartTime(url: string, seconds: number): string {
    const link = new URL(url, window.location.origin);
    link.searchParams.set('t', String(Math.max(0, Math.floor(seconds))));

    return `${link.pathname}${link.search}${link.hash}`;
}

/**
 * How long ago, in words, e.g. "just now", "5 min ago", "3 h ago",
 * "yesterday"; older dates as "21 Nov 2026".
 */
export function formatTimeAgo(value: string | null): string {
    if (!value) {
        return '';
    }

    const minutes = Math.floor(
        (Date.now() - new Date(value).getTime()) / 60_000,
    );

    if (minutes < 1) {
        return 'just now';
    }

    if (minutes < 60) {
        return `${minutes} min ago`;
    }

    if (minutes < 24 * 60) {
        return `${Math.floor(minutes / 60)} h ago`;
    }

    if (minutes < 48 * 60) {
        return 'yesterday';
    }

    return formatDate(value);
}

/**
 * Whether a navigation link points at the current page, or (unless `exact`)
 * at a page above it: "/projects" is active on "/projects/5".
 */
export function isNavHrefActive(
    href: string,
    currentPath: string,
    exact = false,
): boolean {
    const path = new URL(href, 'http://localhost').pathname.replace(/\/$/, '');
    const current = currentPath.replace(/\/$/, '');

    return current === path || (!exact && current.startsWith(`${path}/`));
}
