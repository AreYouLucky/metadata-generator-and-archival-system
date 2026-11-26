import { router } from '@inertiajs/react';
import { findCitationAt, withStartTime } from '@/lib/utilities';
import { useAskMeStore } from '@/stores/ask-me-store';
import type { ChatCitation, ChatLink } from '@/types/chat';

type Props = {
    /** The time as the answer wrote it, e.g. "1:05". */
    label: string;
    seconds: number;
    citations: ChatCitation[];
    /** Where a time off the citations goes (chosen by the server). */
    link: ChatLink | null;
    /** The record whose page the question was asked on, if any. */
    recordId: string | null;
};

/**
 * A time written in an answer, always clickable when it belongs to a video:
 * the cited moment it falls in, else the answer's own record (the page it
 * was asked on, or the only record found), else the first record cited. On
 * that record's own page it plays there; anywhere else it opens the record
 * at that moment, through the link the server chose for this account.
 */
export default function ChatTimeLink({
    label,
    seconds,
    citations,
    link,
    recordId,
}: Props) {
    const page = useAskMeStore((state) => state.page);
    const destination =
        findCitationAt(seconds, citations) ?? link ?? citations[0] ?? null;
    const target = destination?.record_id ?? recordId;
    const here = page !== null && page.recordId === target;
    const action = here
        ? { play: () => page.seek(seconds), title: `Play from ${label}` }
        : destination
          ? {
                play: () =>
                    router.visit(withStartTime(destination.url, seconds)),
                title: `Open ${destination.title} at ${label}`,
            }
          : null;

    if (!action) {
        return <span className="font-mono tabular-nums">{label}</span>;
    }

    return (
        <button
            type="button"
            onClick={action.play}
            className="inline rounded-sm bg-brand/10 px-1 font-mono font-medium text-brand tabular-nums underline decoration-brand/40 underline-offset-2 transition-colors hover:bg-brand/20 hover:decoration-brand focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            aria-label={action.title}
            title={action.title}
        >
            {label}
        </button>
    );
}
