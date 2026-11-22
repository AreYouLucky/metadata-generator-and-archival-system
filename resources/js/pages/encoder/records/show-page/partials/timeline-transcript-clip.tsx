import { useRef, useState } from 'react';
import type { PointerEvent } from 'react';
import { useTimeFormat } from '@/hooks/use-time-format';
import { formatClock } from '@/lib/utilities';
import { cn } from '@/lib/utils';
import type { TranscriptSegment } from '@/types/records';

export type Timing = { start: number; end: number };

type Mode = 'move' | 'start' | 'end';

type Drag = {
    mode: Mode;
    x: number;
    from: Timing;
    latest: Timing;
    moved: boolean;
};

type Props = {
    segment: TranscriptSegment;
    active: boolean;
    pixelsPerSecond: number;
    /** Lines can be dragged and trimmed. */
    editable: boolean;
    /** The line's start must stay between these (the neighbours' starts). */
    bounds: { min: number; max: number };
    /** The video's length; 0 when unknown. */
    duration: number;
    onSeek: (seconds: number) => void;
    onRetime: (segment: TranscriptSegment, timing: Timing) => void;
};

/** Shortest line a trim can leave, in seconds. */
const MIN_LENGTH = 0.1;
/** Pointer travel (px) before a press becomes a drag instead of a click. */
const DRAG_THRESHOLD = 3;

const clamp = (value: number, min: number, max: number) =>
    Math.min(Math.max(value, min), max);
const round = (seconds: number) => Math.round(seconds * 1000) / 1000;

/**
 * The new timing for a drag of `delta` seconds. Neighbouring lines never
 * move or snap: the line may overlap them, but its start stays between theirs
 * so the transcript keeps its order.
 */
function retime(
    drag: Drag,
    delta: number,
    bounds: Props['bounds'],
    duration: number,
): Timing {
    const { start, end } = drag.from;
    const videoEnd = duration > 0 ? duration : Infinity;
    const lowest = Math.max(0, bounds.min);

    if (drag.mode === 'end') {
        return {
            start,
            end: clamp(round(end + delta), start + MIN_LENGTH, videoEnd),
        };
    }

    if (drag.mode === 'start') {
        return {
            start: clamp(
                round(start + delta),
                lowest,
                Math.min(bounds.max, end - MIN_LENGTH),
            ),
            end,
        };
    }

    const length = end - start;
    const moved = clamp(
        round(start + delta),
        lowest,
        Math.max(lowest, Math.min(bounds.max, videoEnd - length)),
    );

    return { start: moved, end: round(moved + length) };
}

/**
 * One transcript line on the timeline. A click seeks to it; when editable,
 * dragging the clip moves the line and dragging either edge trims it.
 */
export default function TimelineTranscriptClip({
    segment,
    active,
    pixelsPerSecond,
    editable,
    bounds,
    duration,
    onSeek,
    onRetime,
}: Props) {
    const drag = useRef<Drag | null>(null);
    const [preview, setPreview] = useState<Timing | null>(null);
    const format = useTimeFormat(formatClock);
    const timing = preview ?? segment;
    const width = Math.max(
        2,
        (timing.end - timing.start) * pixelsPerSecond - 1,
    );
    const handles = editable && width > 14;

    const onPointerDown = (event: PointerEvent<HTMLButtonElement>) => {
        event.stopPropagation();

        if (!editable || event.button !== 0) {
            onSeek(segment.start);

            return;
        }

        const edge = (event.target as HTMLElement).dataset.edge;
        event.currentTarget.setPointerCapture(event.pointerId);
        drag.current = {
            mode: edge === 'start' || edge === 'end' ? edge : 'move',
            x: event.clientX,
            from: { start: segment.start, end: segment.end },
            latest: { start: segment.start, end: segment.end },
            moved: false,
        };
    };

    const onPointerMove = (event: PointerEvent<HTMLButtonElement>) => {
        const current = drag.current;

        if (!current) {
            return;
        }

        const distance = event.clientX - current.x;

        if (!current.moved && Math.abs(distance) < DRAG_THRESHOLD) {
            return;
        }

        current.moved = true;
        current.latest = retime(
            current,
            distance / pixelsPerSecond,
            bounds,
            duration,
        );
        setPreview(current.latest);
    };

    const onPointerUp = () => {
        const current = drag.current;
        drag.current = null;

        if (!current) {
            return;
        }

        setPreview(null);

        if (!current.moved) {
            onSeek(segment.start);

            return;
        }

        if (
            current.latest.start !== current.from.start ||
            current.latest.end !== current.from.end
        ) {
            onRetime(segment, current.latest);
        }
    };

    const onPointerCancel = () => {
        drag.current = null;
        setPreview(null);
    };

    return (
        <button
            type="button"
            tabIndex={-1}
            title={
                editable
                    ? `${segment.text}\nDrag to move · drag an edge to trim`
                    : segment.text
            }
            onPointerDown={onPointerDown}
            onPointerMove={onPointerMove}
            onPointerUp={onPointerUp}
            onPointerCancel={onPointerCancel}
            className={cn(
                'absolute top-1.5 bottom-1.5 touch-none overflow-hidden rounded-sm border px-1 text-left text-[10px] leading-tight text-white/90 select-none',
                editable && 'cursor-grab active:cursor-grabbing',
                preview && 'z-10 ring-1 ring-white/70',
                active || preview
                    ? 'border-brand bg-brand/85'
                    : 'border-sky-800/20 bg-sky-700/80 hover:bg-sky-600/90',
            )}
            style={{ left: timing.start * pixelsPerSecond, width }}
        >
            {width > 36 && (
                <span
                    className={cn(
                        'line-clamp-2',
                        preview && 'font-mono tabular-nums',
                    )}
                >
                    {preview
                        ? `${format(preview.start)} – ${format(preview.end)}`
                        : segment.text}
                </span>
            )}
            {handles && (
                <>
                    <span
                        data-edge="start"
                        aria-hidden
                        className="absolute inset-y-0 left-0 w-1.5 cursor-ew-resize hover:bg-white/40"
                    />
                    <span
                        data-edge="end"
                        aria-hidden
                        className="absolute inset-y-0 right-0 w-1.5 cursor-ew-resize hover:bg-white/40"
                    />
                </>
            )}
        </button>
    );
}
