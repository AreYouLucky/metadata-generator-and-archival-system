import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { getErrorMessage } from '@/lib/utilities';
import { isAxiosError } from 'axios';

/**
 * Local edits of one Archive sub-tab: starts from the saved values, resets
 * when they change on the server (after a save or a reload), and keeps the
 * validation errors of the last save by field.
 */
export function useArchiveForm<T extends object>(saved: T) {
    const [values, setValues] = useState<T>(saved);
    const [errors, setErrors] = useState<Partial<Record<keyof T, string>>>({});
    const savedKey = JSON.stringify(saved);

    // A reload with new saved values replaces the form.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    useEffect(() => setValues(saved), [savedKey]);

    const dirty = JSON.stringify(values) !== savedKey;

    const change = (changes: Partial<T>) =>
        setValues((current) => ({ ...current, ...changes }));

    /** Pass to a mutation's options: a toast, and the field errors. */
    const feedback = (success: string) => ({
        onSuccess: () => {
            setErrors({});
            toast.success(success);
        },
        onError: (error: unknown) => {
            const fields = isAxiosError<{ errors?: Record<string, string[]> }>(
                error,
            )
                ? error.response?.data?.errors
                : undefined;
            setErrors(
                Object.fromEntries(
                    Object.entries(fields ?? {}).map(([key, messages]) => [
                        key,
                        messages[0],
                    ]),
                ) as Partial<Record<keyof T, string>>,
            );
            toast.error(
                getErrorMessage(error, 'The changes could not be saved.'),
            );
        },
    });

    return { values, change, dirty, errors, feedback };
}
