import type { PointerEvent as ReactPointerEvent } from 'react';
import { formatTimestamp } from '@/lib/utilities';
import { useWorkspaceStore } from '@/stores/workspace-store';

/** Seconds between labelled ticks; the smallest that leaves ~80 px between labels. */
const STEPS = [1, 2, 5, 10, 15, 30, 60, 120, 300, 600, 900, 1800, 3600];

type Props = {
    pixelsPerSecond: number;
    range: { start: number; end: number };
    duration: number;
    onScrub: (clientX: number) => number;
};

/**
 * Time ruler. Press and drag anywhere on it to scrub the playhead.
 */
export default function TimelineRuler({
    pixelsPerSecond,
    range,
    duration,
    onScrub,
}: Props) {
    const seek = useWorkspaceStore((state) => state.seek);
    const step =
        STEPS.find((seconds) => seconds * pixelsPerSecond >= 80) ??
        STEPS[STEPS.length - 1];
    const minor = step / 5;
    const ticks: number[] = [];

    for (
        let time = Math.floor(range.start / minor) * minor;
        time <= Math.min(range.end, duration);
        time += minor
    ) {
        ticks.push(Math.round(time * 1000) / 1000);
    }

    const scrub = (event: ReactPointerEvent<HTMLDivElement>) =>
        seek(onScrub(event.clientX));

    return (
        <div
            className="relative h-7 cursor-ew-resize touch-none border-b border-border bg-background/60 select-none"
            onPointerDown={(event) => {
                event.currentTarget.setPointerCapture(event.pointerId);
                scrub(event);
            }}
            onPointerMove={(event) => {
                if (event.currentTarget.hasPointerCapture(event.pointerId)) {
                    scrub(event);
                }
            }}
            aria-hidden
        >
            {ticks.map((time) => {
                const major =
                    Math.abs(time / step - Math.round(time / step)) < 1e-6;

                return (
                    <div
                        key={time}
                        className="absolute bottom-0"
                        style={{ left: time * pixelsPerSecond }}
                    >
                        <div
                            className={
                                major
                                    ? 'h-3 w-px bg-neutral-500'
                                    : 'h-1.5 w-px bg-neutral-700'
                            }
                        />
                        {major && (
                            <span className="absolute bottom-3 left-1 font-mono text-[10px] whitespace-nowrap text-muted-foreground">
                                {formatTimestamp(time)}
                            </span>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
