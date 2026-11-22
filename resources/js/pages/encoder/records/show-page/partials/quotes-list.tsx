import { Quote as QuoteIcon, Scissors, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatTimestamp } from '@/lib/utilities';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { Quote } from '@/types/records';

type Props = {
    quotes: Quote[];
    disabled: boolean;
    onChange: (quotes: Quote[]) => void;
};

/**
 * Key quotes picked by the AI, word for word from the transcript: click one
 * to play it; remove the ones that don't fit.
 */
export default function QuotesList({ quotes, disabled, onChange }: Props) {
    const seek = useWorkspaceStore((state) => state.seek);
    const markClip = useWorkspaceStore((state) => state.markClip);
    const setInspectorTab = useWorkspaceStore((state) => state.setInspectorTab);

    const clipQuote = (quote: Quote) => {
        const title =
            quote.text.length > 80 ? `${quote.text.slice(0, 79)}…` : quote.text;
        markClip(quote.start, Math.max(quote.end, quote.start + 1), title);
        setInspectorTab('clips');
    };

    return (
        <div className="grid gap-2">
            <h3 className="text-sm font-medium">Key quotes</h3>
            {quotes.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No quotes stood out in this video.
                </p>
            ) : (
                <ul className="grid gap-2">
                    {quotes.map((quote, index) => (
                        <li
                            key={`${quote.start}-${index}`}
                            className="group flex items-start gap-2 rounded-md bg-muted/60 p-2"
                        >
                            <QuoteIcon
                                className="mt-0.5 size-3.5 shrink-0 text-brand"
                                aria-hidden
                            />
                            <button
                                type="button"
                                onClick={() => seek(quote.start)}
                                className="min-w-0 flex-1 rounded text-left text-sm leading-relaxed focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                aria-label={`Play the quote at ${formatTimestamp(quote.start)}`}
                            >
                                <span className="mr-2 font-mono text-xs text-brand tabular-nums">
                                    {formatTimestamp(quote.start)}
                                </span>
                                “{quote.text}”
                            </button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-6 opacity-0 group-hover:opacity-100 focus-visible:opacity-100"
                                onClick={() => clipQuote(quote)}
                                aria-label="Make a clip of this quote"
                                title="Clip this quote"
                            >
                                <Scissors className="size-3" />
                            </Button>
                            {!disabled && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-6 opacity-0 group-hover:opacity-100 focus-visible:opacity-100"
                                    onClick={() =>
                                        onChange(
                                            quotes.filter(
                                                (_, position) =>
                                                    position !== index,
                                            ),
                                        )
                                    }
                                    aria-label="Remove this quote"
                                >
                                    <X aria-hidden />
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
