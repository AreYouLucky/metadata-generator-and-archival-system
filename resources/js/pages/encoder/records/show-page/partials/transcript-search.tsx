import { ChevronDown, ChevronUp, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { TRANSCRIPT_SEARCH_ID } from '@/hooks/use-keyboard-shortcuts';

type Props = {
    query: string;
    onQueryChange: (query: string) => void;
    matchCount: number;
    matchPosition: number;
    onNext: () => void;
    onPrevious: () => void;
    onSubmit: () => void;
};

/**
 * Highlights every match ("3 of 12"); Enter or the arrows jump to a match
 * and seek the player there.
 */
export default function TranscriptSearch({
    query,
    onQueryChange,
    matchCount,
    matchPosition,
    onNext,
    onPrevious,
    onSubmit,
}: Props) {
    return (
        <form
            role="search"
            className="flex w-full items-center gap-1"
            onSubmit={(event) => {
                event.preventDefault();
                onSubmit();
            }}
        >
            <div className="relative min-w-0 flex-1">
                <Search
                    className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden
                />
                <Input
                    id={TRANSCRIPT_SEARCH_ID}
                    type="search"
                    value={query}
                    onChange={(event) => onQueryChange(event.target.value)}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter' && event.shiftKey) {
                            event.preventDefault();
                            onPrevious();
                        }
                    }}
                    placeholder="Search transcript ( / )"
                    aria-label="Search transcript"
                    className="h-8 w-full pl-8"
                />
            </div>
            {query.trim() !== '' && (
                <>
                    <span
                        className="min-w-14 text-center text-xs text-muted-foreground tabular-nums"
                        aria-live="polite"
                    >
                        {matchCount === 0
                            ? 'No matches'
                            : `${matchPosition + 1} of ${matchCount}`}
                    </span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={onPrevious}
                        disabled={matchCount === 0}
                        aria-label="Previous match"
                    >
                        <ChevronUp aria-hidden />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={onNext}
                        disabled={matchCount === 0}
                        aria-label="Next match"
                    >
                        <ChevronDown aria-hidden />
                    </Button>
                </>
            )}
        </form>
    );
}
