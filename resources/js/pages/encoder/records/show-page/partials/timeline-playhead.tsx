import { useEffect } from 'react';
import type { RefObject } from 'react';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { TimelineViewport } from './timeline';

type Props = {
    pixelsPerSecond: number;
    viewport: TimelineViewport;
    scroller: RefObject<HTMLDivElement | null>;
};

/**
 * The playhead line. While playing, the timeline pages along so the playhead
 * stays in view. Its own component, so only it re-renders on every frame.
 */
export default function TimelinePlayhead({
    pixelsPerSecond,
    viewport,
    scroller,
}: Props) {
    const currentTime = useWorkspaceStore((state) => state.currentTime);
    const paused = useWorkspaceStore((state) => state.paused);
    const x = currentTime * pixelsPerSecond;

    useEffect(() => {
        const element = scroller.current;

        if (paused || !element || viewport.width === 0) {
            return;
        }

        if (x < viewport.left || x > viewport.left + viewport.width * 0.9) {
            element.scrollLeft = Math.max(0, x - viewport.width * 0.1);
        }
    }, [x, paused, viewport, scroller]);

    return (
        <div
            className="pointer-events-none absolute top-0 bottom-0 z-10 w-px bg-red-500"
            style={{ transform: `translateX(${x}px)` }}
            aria-hidden
        >
            <div className="absolute -top-0 -left-[5px] size-0 border-x-[5px] border-t-[7px] border-x-transparent border-t-red-500" />
        </div>
    );
}
