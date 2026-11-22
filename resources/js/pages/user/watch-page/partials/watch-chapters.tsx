import { cn } from '@/lib/utils';
import { findSegmentIndex, formatTimestamp } from '@/lib/utilities';

type Props = {
    chapters: { start: number; title: string }[];
    currentTime: number;
    onSeek: (seconds: number) => void;
};

/**
 * Chapters of the video; the current one is highlighted and a click jumps
 * to it.
 */
export default function WatchChapters({
    chapters,
    currentTime,
    onSeek,
}: Props) {
    const active = findSegmentIndex(
        chapters.map((chapter, index) => ({
            start: chapter.start,
            end: chapters[index + 1]?.start ?? Number.POSITIVE_INFINITY,
        })),
        currentTime,
    );

    return (
        <ol className="grid gap-1">
            {chapters.map((chapter, index) => (
                <li key={`${chapter.start}-${index}`}>
                    <button
                        type="button"
                        onClick={() => onSeek(chapter.start)}
                        className={cn(
                            'flex w-full gap-3 rounded-md px-3 py-1.5 text-left text-sm transition hover:bg-muted',
                            index === active && 'bg-brand-muted font-medium',
                        )}
                    >
                        <span className="w-12 shrink-0 text-muted-foreground tabular-nums">
                            {formatTimestamp(chapter.start)}
                        </span>
                        {chapter.title}
                    </button>
                </li>
            ))}
        </ol>
    );
}
