import {
    useCallback,
    useEffect,
    useLayoutEffect,
    useRef,
    useState,
} from 'react';
import type { PointerEvent as ReactPointerEvent } from 'react';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type {
    Chapter,
    TranscriptSegment,
    WorkspaceMedia,
} from '@/types/records';
import TimelineChaptersTrack from './timeline-chapters-track';
import TimelinePlayhead from './timeline-playhead';
import TimelineRuler from './timeline-ruler';
import TimelineTranscriptTrack from './timeline-transcript-track';
import TimelineWaveformTrack from './timeline-waveform-track';
import TimelineZoom from './timeline-zoom';

/** Widest zoom: 200 px per second. "Fit" shows the whole video. */
const MAX_PIXELS_PER_SECOND = 200;

const TRACKS = [
    { key: 'chapters', label: 'Chapters', height: 'h-9' },
    { key: 'transcript', label: 'Transcript', height: 'h-12' },
    { key: 'audio', label: 'Audio', height: 'h-16' },
] as const;

type Props = {
    recordId: string;
    media: WorkspaceMedia;
    segments: TranscriptSegment[];
    chapters: Chapter[];
};

export type TimelineViewport = { left: number; width: number };

/**
 * Editor timeline: time ruler (click or drag to scrub), chapters,
 * transcript and audio waveform tracks, and a playhead that follows playback.
 * Only the visible part of each track is rendered.
 */
export default function Timeline({
    recordId,
    media,
    segments,
    chapters,
}: Props) {
    const scroller = useRef<HTMLDivElement>(null);
    const pendingScroll = useRef<number | null>(null);
    const frame = useRef(0);
    const [viewport, setViewport] = useState<TimelineViewport>({
        left: 0,
        width: 0,
    });
    const [zoom, setZoom] = useState<number | null>(null);
    const seek = useWorkspaceStore((state) => state.seek);
    const duration =
        useWorkspaceStore((state) => state.duration) ||
        media.duration ||
        (segments.at(-1)?.end ?? 0);

    const fitPixelsPerSecond =
        duration > 0 && viewport.width > 0 ? viewport.width / duration : 10;
    const pixelsPerSecond = zoom ?? fitPixelsPerSecond;
    const contentWidth = Math.max(
        viewport.width,
        Math.ceil(duration * pixelsPerSecond),
    );

    useEffect(() => {
        const element = scroller.current;

        if (!element) {
            return;
        }

        const observer = new ResizeObserver(() =>
            setViewport({
                left: element.scrollLeft,
                width: element.clientWidth,
            }),
        );
        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    // After a zoom, keep the playhead where it was on screen.
    useLayoutEffect(() => {
        if (scroller.current && pendingScroll.current !== null) {
            scroller.current.scrollLeft = pendingScroll.current;
            pendingScroll.current = null;
        }
    }, [pixelsPerSecond]);

    const changeZoom = (next: number | null) => {
        const time = useWorkspaceStore.getState().currentTime;
        const nextPixels = next ?? fitPixelsPerSecond;
        const onScreen = time * pixelsPerSecond - viewport.left;
        pendingScroll.current = Math.max(0, time * nextPixels - onScreen);
        setZoom(
            next === null || next <= fitPixelsPerSecond * 1.001 ? null : next,
        );
    };

    const onScroll = () => {
        cancelAnimationFrame(frame.current);
        frame.current = requestAnimationFrame(() => {
            const element = scroller.current;

            if (element) {
                setViewport({
                    left: element.scrollLeft,
                    width: element.clientWidth,
                });
            }
        });
    };

    const timeAt = useCallback(
        (clientX: number) => {
            const element = scroller.current;

            if (!element || pixelsPerSecond <= 0) {
                return 0;
            }

            const x =
                clientX -
                element.getBoundingClientRect().left +
                element.scrollLeft;

            return Math.min(duration, Math.max(0, x / pixelsPerSecond));
        },
        [duration, pixelsPerSecond],
    );

    const seekToPointer = (event: ReactPointerEvent) =>
        seek(timeAt(event.clientX));

    const range = {
        start: Math.max(0, viewport.left / pixelsPerSecond - 1),
        end: (viewport.left + viewport.width) / pixelsPerSecond + 1,
    };

    return (
        <section
            aria-label="Timeline"
            className="flex shrink-0 flex-col border-t border-white/10 bg-neutral-900"
        >
            <header className="flex h-9 items-center justify-between gap-3 border-b border-white/10 px-3">
                <h2 className="text-[11px] font-semibold tracking-wider text-neutral-400 uppercase">
                    Timeline
                </h2>
                <TimelineZoom
                    pixelsPerSecond={pixelsPerSecond}
                    fitPixelsPerSecond={fitPixelsPerSecond}
                    maxPixelsPerSecond={MAX_PIXELS_PER_SECOND}
                    onZoom={changeZoom}
                />
            </header>

            <div className="flex">
                <div className="w-24 shrink-0 border-r border-white/10 text-[11px] text-neutral-400">
                    <div className="h-7 border-b border-white/10" />
                    {TRACKS.map((track) => (
                        <div
                            key={track.key}
                            className={`${track.height} flex items-center border-b border-white/5 px-3`}
                        >
                            {track.label}
                        </div>
                    ))}
                </div>

                <div
                    ref={scroller}
                    onScroll={onScroll}
                    className="relative min-w-0 flex-1 overflow-x-auto overflow-y-hidden"
                >
                    <div className="relative" style={{ width: contentWidth }}>
                        <TimelineRuler
                            pixelsPerSecond={pixelsPerSecond}
                            range={range}
                            duration={duration}
                            onScrub={timeAt}
                        />
                        <div
                            className="h-9 border-b border-white/5"
                            onPointerDown={seekToPointer}
                        >
                            <TimelineChaptersTrack
                                chapters={chapters}
                                duration={duration}
                                pixelsPerSecond={pixelsPerSecond}
                                range={range}
                            />
                        </div>
                        <div
                            className="h-12 border-b border-white/5"
                            onPointerDown={seekToPointer}
                        >
                            <TimelineTranscriptTrack
                                segments={segments}
                                pixelsPerSecond={pixelsPerSecond}
                                range={range}
                            />
                        </div>
                        <div
                            className="h-16 border-b border-white/5"
                            onPointerDown={seekToPointer}
                        >
                            <TimelineWaveformTrack
                                recordId={recordId}
                                available={media.has_waveform}
                                pixelsPerSecond={pixelsPerSecond}
                                viewport={viewport}
                            />
                        </div>
                        <TimelinePlayhead
                            pixelsPerSecond={pixelsPerSecond}
                            viewport={viewport}
                            scroller={scroller}
                        />
                    </div>
                </div>
            </div>
        </section>
    );
}
