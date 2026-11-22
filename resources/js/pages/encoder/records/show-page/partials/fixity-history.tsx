import { CircleAlert, CircleCheck, ShieldCheck } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useRunFixity } from '@/hooks/use-archive';
import {
    formatDateTime,
    formatEnumLabel,
    getErrorMessage,
} from '@/lib/utilities';
import type { FixityEntry } from '@/types/archive';

type Props = {
    recordId: string;
    entries: FixityEntry[];
};

/**
 * Fixity checks of the master and mezzanine files, newest first, and
 * "Check now". Results arrive in the background and refresh this list.
 */
export default function FixityHistory({ recordId, entries }: Props) {
    const run = useRunFixity(recordId);

    return (
        <section className="grid grid-cols-1 gap-2 border-t border-white/10 pt-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="text-sm font-medium">Fixity checks</h3>
                <Button
                    size="sm"
                    variant="secondary"
                    className="h-7"
                    disabled={run.isPending}
                    onClick={() =>
                        run.mutate(undefined, {
                            onSuccess: ({ queued }) =>
                                toast.success(
                                    `Checking ${queued} ${queued === 1 ? 'file' : 'files'}… the result appears here.`,
                                ),
                            onError: (error) =>
                                toast.error(getErrorMessage(error)),
                        })
                    }
                >
                    {run.isPending ? <Spinner /> : <ShieldCheck aria-hidden />}
                    Check now
                </Button>
            </div>
            {entries.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No checks yet. A scheduled check re-hashes every master
                    regularly.
                </p>
            ) : (
                <ul className="grid grid-cols-1 gap-1 text-xs">
                    {entries.map((entry) => (
                        <li
                            key={entry.id}
                            className="flex flex-wrap items-center gap-x-2 gap-y-0.5"
                        >
                            {entry.matched ? (
                                <CircleCheck
                                    className="size-3.5 shrink-0 text-emerald-400"
                                    aria-label="Matched"
                                />
                            ) : (
                                <CircleAlert
                                    className="size-3.5 shrink-0 text-red-400"
                                    aria-label="Did not match"
                                />
                            )}
                            <span className="text-neutral-300">
                                {formatEnumLabel(entry.role ?? 'file')} ·{' '}
                                {entry.algorithm.toUpperCase()}
                            </span>
                            <span className="text-neutral-500">
                                {formatEnumLabel(entry.triggered_by)}
                            </span>
                            <span className="ml-auto text-neutral-500 tabular-nums">
                                {formatDateTime(entry.checked_at)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
