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
        <ol className="grid max-h-80 gap-1 overflow-y-auto p-3">
            {chapters.map((chapter, index) => (
                <li key={`${chapter.start}-${index}`}>
                    <button
                        type="button"
                        onClick={() => onSeek(chapter.start)}
                        aria-current={index === active ? 'true' : undefined}
                        className={cn(
                            'flex w-full gap-3 rounded-lg px-3 py-2 text-left text-sm transition',
                            index === active
                                ? 'bg-sky-600 font-medium text-white'
                                : 'hover:bg-muted',
                        )}
                    >
                        <span
                            className={cn(
                                'w-12 shrink-0 tabular-nums',
                                index === active
                                    ? 'text-white/80'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {formatTimestamp(chapter.start)}
                        </span>
                        {chapter.title}
                    </button>
                </li>
            ))}
        </ol>
    );
}
