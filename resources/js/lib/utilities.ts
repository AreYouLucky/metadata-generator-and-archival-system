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
 * Local time with seconds, e.g. "14:03:27".
 */
export function formatClockTime(value: string | Date): string {
    return new Date(value).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}
