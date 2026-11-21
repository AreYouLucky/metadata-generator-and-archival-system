import { useWorkspaceStore } from '@/stores/workspace-store';
import type { Chapter } from '@/types/records';

const COLOURS = [
    'bg-sky-700/70 hover:bg-sky-600/80',
    'bg-violet-700/70 hover:bg-violet-600/80',
    'bg-teal-700/70 hover:bg-teal-600/80',
    'bg-amber-700/70 hover:bg-amber-600/80',
];

type Props = {
    chapters: Chapter[];
    duration: number;
    pixelsPerSecond: number;
    range: { start: number; end: number };
};

/**
 * Chapters as coloured clips; each runs until the next one starts.
 */
export default function TimelineChaptersTrack({
    chapters,
    duration,
    pixelsPerSecond,
    range,
}: Props) {
    const seek = useWorkspaceStore((state) => state.seek);

    return (
        <div className="relative h-full">
            {chapters.map((chapter, index) => {
                const end = chapters[index + 1]?.start ?? duration;

                if (end < range.start || chapter.start > range.end) {
                    return null;
                }

                const width = Math.max(
                    2,
                    (end - chapter.start) * pixelsPerSecond - 2,
                );

                return (
                    <button
                        key={`${chapter.start}-${index}`}
                        type="button"
                        tabIndex={-1}
                        title={chapter.title}
                        onPointerDown={(event) => {
                            event.stopPropagation();
                            seek(chapter.start);
                        }}
                        className={`absolute top-1 bottom-1 overflow-hidden rounded-sm px-1.5 text-left text-[11px] leading-7 whitespace-nowrap text-white ${COLOURS[index % COLOURS.length]}`}
                        style={{ left: chapter.start * pixelsPerSecond, width }}
                    >
                        {width > 40 && chapter.title}
                    </button>
                );
            })}
        </div>
    );
}
