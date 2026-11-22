import { useVirtualizer } from '@tanstack/react-virtual';
import { useMemo, useRef } from 'react';
import { cn } from '@/lib/utils';
import { findSegmentIndex, formatTimestamp } from '@/lib/utilities';
import type { WatchTranscriptLine } from '@/types/catalogue';

type Props = {
    lines: WatchTranscriptLine[];
    currentTime: number;
    onSeek: (seconds: number) => void;
};

/**
 * The transcript beside the public player: the line being spoken is
 * highlighted, and a click jumps there. Virtualised, as long videos have
 * thousands of lines.
 */
export default function WatchTranscript({ lines, currentTime, onSeek }: Props) {
    const scroller = useRef<HTMLDivElement>(null);
    const active = useMemo(
        () => findSegmentIndex(lines, currentTime),
        [lines, currentTime],
    );
    const virtualizer = useVirtualizer({
        count: lines.length,
        getScrollElement: () => scroller.current,
        estimateSize: () => 56,
        overscan: 8,
    });

    if (lines.length === 0) {
        return (
            <p className="p-4 text-sm text-muted-foreground">
                No transcript for this video.
            </p>
        );
    }

    return (
        <div ref={scroller} className="h-96 overflow-y-auto lg:h-[32rem]">
            <ol
                className="relative w-full"
                style={{ height: virtualizer.getTotalSize() }}
            >
                {virtualizer.getVirtualItems().map((item) => {
                    const line = lines[item.index];

                    return (
                        <li
                            key={item.key}
                            data-index={item.index}
                            ref={virtualizer.measureElement}
                            className="absolute top-0 left-0 w-full"
                            style={{ transform: `translateY(${item.start}px)` }}
                        >
                            <button
                                type="button"
                                onClick={() => onSeek(line.start)}
                                className={cn(
                                    'flex w-full gap-3 px-4 py-2 text-left text-sm transition hover:bg-muted focus-visible:bg-muted focus-visible:outline-none',
                                    item.index === active && 'bg-brand-muted',
                                )}
                            >
                                <span className="w-12 shrink-0 pt-0.5 text-xs text-muted-foreground tabular-nums">
                                    {formatTimestamp(line.start)}
                                </span>
                                <span>
                                    {line.speaker && (
                                        <span className="font-medium">
                                            {line.speaker}:{' '}
                                        </span>
                                    )}
                                    {line.text}
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ol>
        </div>
    );
}
