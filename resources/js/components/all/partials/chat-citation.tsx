import { router } from '@inertiajs/react';
import { Play } from 'lucide-react';
import { formatTimestamp, withStartTime } from '@/lib/utilities';
import { useAskMeStore } from '@/stores/ask-me-store';
import type { ChatCitation as Citation } from '@/types/chat';

type Props = { citation: Citation };

/**
 * A moment an answer relies on. On that record's own page it plays there;
 * elsewhere it opens the record at that moment (the workspace for staff who
 * may open it, the public watch page otherwise; the server chose the link).
 */
export default function ChatCitation({ citation }: Props) {
    const page = useAskMeStore((state) => state.page);
    const here = page?.recordId === citation.record_id;
    const time = formatTimestamp(citation.start);

    return (
        <button
            type="button"
            onClick={() =>
                here
                    ? page.seek(citation.start)
                    : router.visit(withStartTime(citation.url, citation.start))
            }
            className="inline-flex max-w-full items-center gap-1 rounded-full bg-brand/15 px-2 py-0.5 text-[11px] text-brand transition-colors hover:bg-brand/25 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            aria-label={`Play ${citation.title} from ${time}`}
            title={citation.title}
        >
            <Play className="size-2.5 shrink-0 fill-current" aria-hidden />
            {!here && (
                <span className="max-w-32 truncate">{citation.title} ·</span>
            )}
            <span className="font-mono tabular-nums">{time}</span>
        </button>
    );
}
