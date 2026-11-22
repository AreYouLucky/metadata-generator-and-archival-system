import { useRef, useState } from 'react';
import type { PointerEvent as ReactPointerEvent } from 'react';
import { formatTimestamp } from '@/lib/utilities';
import { cn } from '@/lib/utils';
import { useMetadataDraftStore } from '@/stores/metadata-draft-store';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { Chapter } from '@/types/records';

const COLOURS = [
    'bg-blue-700/70 hover:bg-blue-600/80',
    'bg-violet-700/70 hover:bg-violet-600/80',
    'bg-sky-700/70 hover:bg-sky-600/80',
    'bg-amber-700/70 hover:bg-amber-600/80',
];

/** Pointer travel before a press counts as a drag rather than a click. */
const DRAG_THRESHOLD_PX = 4;

type Props = {
    chapters: Chapter[];
    duration: number;
    pixelsPerSecond: number;
    range: { start: number; end: number };
    editable: boolean;
};

type Drag = {
    index: number;
    originX: number;
    originStart: number;
    moved: boolean;
};

/**
 * Chapters as coloured clips; each runs until the next one starts. Click one
 * to jump there; when editable, drag it sideways to move where it starts (it
 * stays between its neighbours).
 */
export default function TimelineChaptersTrack({
    chapters,
    duration,
    pixelsPerSecond,
    range,
    editable,
}: Props) {
    const seek = useWorkspaceStore((state) => state.seek);
    const moveChapter = useMetadataDraftStore((state) => state.moveChapter);
    const drag = useRef<Drag | null>(null);
    const [dragging, setDragging] = useState<number | null>(null);

    const onPointerDown = (
        event: ReactPointerEvent<HTMLButtonElement>,
        index: number,
    ) => {
        event.stopPropagation();

        if (!editable) {
            seek(chapters[index].start);

            return;
        }

        event.currentTarget.setPointerCapture(event.pointerId);
        drag.current = {
            index,
            originX: event.clientX,
            originStart: chapters[index].start,
            moved: false,
        };
    };

    const onPointerMove = (event: ReactPointerEvent<HTMLButtonElement>) => {
        const current = drag.current;

        if (!current || pixelsPerSecond <= 0) {
            return;
        }

        const offset = event.clientX - current.originX;

        if (!current.moved && Math.abs(offset) < DRAG_THRESHOLD_PX) {
            return;
        }

        if (!current.moved) {
            current.moved = true;
            setDragging(current.index);
        }

        moveChapter(
            current.index,
            Math.min(
                Math.max(0, duration - 1),
                current.originStart + offset / pixelsPerSecond,
            ),
        );
    };

    const onPointerUp = () => {
        const current = drag.current;
        drag.current = null;
        setDragging(null);

        if (current && !current.moved) {
            seek(chapters[current.index].start);
        }
    };

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
                const isDragging = dragging === index;

                return (
                    <button
                        key={index}
                        type="button"
                        tabIndex={-1}
                        title={
                            editable
                                ? `${chapter.title} · drag to move`
                                : chapter.title
                        }
                        onPointerDown={(event) => onPointerDown(event, index)}
                        onPointerMove={onPointerMove}
                        onPointerUp={onPointerUp}
                        onPointerCancel={onPointerUp}
                        className={cn(
                            'absolute top-1 bottom-1 touch-none overflow-hidden rounded-sm border-l-2 border-white/70 px-1.5 text-left text-[11px] leading-7 whitespace-nowrap text-white select-none',
                            COLOURS[index % COLOURS.length],
                            editable && 'cursor-grab',
                            isDragging &&
                                'z-10 cursor-grabbing ring-2 ring-brand',
                        )}
                        style={{ left: chapter.start * pixelsPerSecond, width }}
                    >
                        {isDragging
                            ? formatTimestamp(chapter.start)
                            : width > 40 && chapter.title}
                    </button>
                );
            })}
        </div>
    );
}
