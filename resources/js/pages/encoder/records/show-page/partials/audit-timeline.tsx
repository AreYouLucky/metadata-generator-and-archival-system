import { formatDateTime, formatEnumLabel } from '@/lib/utilities';
import type { AuditEntry } from '@/types/archive';

/** The record's audit log (append-only), newest first. */
export default function AuditTimeline({ entries }: { entries: AuditEntry[] }) {
    return (
        <section className="grid gap-2 border-t border-white/10 pt-4">
            <h3 className="text-sm font-medium">History</h3>
            <ol className="grid gap-2 border-l border-white/10 pl-3">
                {entries.map((entry) => (
                    <li key={entry.id} className="relative text-xs">
                        <span
                            className="absolute top-1.5 -left-[16.5px] size-2 rounded-full bg-brand"
                            aria-hidden
                        />
                        <p>
                            <span className="font-medium">
                                {formatEnumLabel(entry.action)}
                            </span>
                            <span className="text-neutral-500">
                                {' '}
                                · {entry.performed_by} ·{' '}
                                {formatDateTime(entry.performed_at)}
                            </span>
                        </p>
                        {entry.remarks && (
                            <p className="break-words text-neutral-400">
                                {entry.remarks}
                            </p>
                        )}
                    </li>
                ))}
            </ol>
        </section>
    );
}
