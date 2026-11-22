import {
    useCallback,
    useEffect,
    useLayoutEffect,
    useRef,
    useState,
} from 'react';
import type {
    MouseEvent as ReactMouseEvent,
    PointerEvent as ReactPointerEvent,
} from 'react';
import { toast } from 'sonner';
import { useDraftChapters } from '@/hooks/use-metadata-draft';
import { formatTimestamp } from '@/lib/utilities';
import { useMetadataDraftStore } from '@/stores/metadata-draft-store';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type {
    ActiveMetadata,
    TranscriptSegment,
    WorkspaceMedia,
} from '@/types/records';
import type { Scene, Shot } from '@/types/visuals';
import TimelineChapterControls from './timeline-chapter-controls';
import TimelineChaptersTrack from './timeline-chapters-track';
import TimelineClipRange from './timeline-clip-range';
import TimelinePlayhead from './timeline-playhead';
import TimelineRuler from './timeline-ruler';
import TimelineScenesTrack from './timeline-scenes-track';
import TimelineTranscriptTrack from './timeline-transcript-track';
import TimelineWaveformTrack from './timeline-waveform-track';
import TimelineZoom from './timeline-zoom';

/** Widest zoom: 200 px per second. "Fit" shows the whole video. */
const MAX_PIXELS_PER_SECOND = 200;

// The audio track takes whatever height is left, so the timeline fills its share.
const TRACKS = [
    { key: 'chapters', label: 'Chapters', height: 'h-9 shrink-0' },
    { key: 'transcript', label: 'Transcript', height: 'h-12 shrink-0' },
    { key: 'scenes', label: 'Scenes', height: 'h-8 shrink-0' },
    { key: 'audio', label: 'Audio', height: 'min-h-12 flex-1' },
] as const;

type Props = {
    recordId: string;
    media: WorkspaceMedia;
    segments: TranscriptSegment[];
    /** The active generation; its chapters are edited through the shared draft. */
    metadata: ActiveMetadata | null;
    /** Detected shots and named scenes (visual analysis). */
    shots: Shot[];
    scenes: Scene[];
    canEdit: boolean;
    generating: boolean;
    /** A line's timing changed on the transcript track. */
    onSegmentSaved: (
        saved: Pick<TranscriptSegment, 'id'> & Partial<TranscriptSegment>,
    ) => void;
};

export type TimelineViewport = { left: number; width: number };

/**
 * Editor timeline: time ruler (click or drag to scrub), chapters,
 * transcript, scenes (shots and named scenes) and audio waveform tracks, and a playhead that follows playback.
 * Only the visible part of each track is rendered. Encoders add chapters at
 * the playhead (or by double-clicking the chapters track) and drag them, and
 * drag or trim transcript lines to fix their timing.
 */
export default function Timeline({
    recordId,
    media,
    segments,
    metadata,
    shots,
    scenes,
    canEdit,
    generating,
    onSegmentSaved,
}: Props) {
    const chapters = useDraftChapters(metadata);
    const addChapter = useMetadataDraftStore((state) => state.addChapter);
    const editable = canEdit && metadata !== null && !generating;
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

    const addChapterAt = (time: number) => {
        if (addChapter(time)) {
            toast.success(
                `Chapter added at ${formatTimestamp(Math.round(time))}. Name it in the Chapters tab, then save.`,
            );
        } else {
            toast.error('There is already a chapter at that time.');
        }
    };

    const addChapterOnTrack = (event: ReactMouseEvent) => {
        // On empty track only: a double-click on a chapter is two clicks on it.
        if (editable && !(event.target as HTMLElement).closest('button')) {
            addChapterAt(timeAt(event.clientX));
        }
    };

    const range = {
        start: Math.max(0, viewport.left / pixelsPerSecond - 1),
        end: (viewport.left + viewport.width) / pixelsPerSecond + 1,
    };

    return (
        <section
            aria-label="Timeline"
            className="flex min-h-60 shrink-0 flex-col border-t border-border bg-card xl:h-[calc((100svh-1rem)*0.3)] xl:min-h-60"
        >
            <header className="flex h-9 shrink-0 items-center justify-between gap-3 border-b border-border px-3">
                <div className="flex min-w-0 items-center gap-3">
                    <h2 className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                        Timeline
                    </h2>
                    {canEdit && (
                        <TimelineChapterControls
                            recordId={recordId}
                            editable={editable}
                            hasMetadata={metadata !== null}
                            onAdd={() =>
                                addChapterAt(
                                    useWorkspaceStore.getState().currentTime,
                                )
                            }
                        />
                    )}
                </div>
                <TimelineZoom
                    pixelsPerSecond={pixelsPerSecond}
                    fitPixelsPerSecond={fitPixelsPerSecond}
                    maxPixelsPerSecond={MAX_PIXELS_PER_SECOND}
                    onZoom={changeZoom}
                />
            </header>

            <div className="flex min-h-0 flex-1">
                <div className="flex w-24 shrink-0 flex-col border-r border-border text-[11px] text-muted-foreground">
                    <div className="h-7 shrink-0 border-b border-border" />
                    {TRACKS.map((track) => (
                        <div
                            key={track.key}
                            className={`${track.height} flex items-center border-b border-border px-3`}
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
                    <div
                        className="relative flex h-full flex-col"
                        style={{ width: contentWidth }}
                    >
                        <TimelineRuler
                            pixelsPerSecond={pixelsPerSecond}
                            range={range}
                            duration={duration}
                            onScrub={timeAt}
                        />
                        <div
                            className="h-9 shrink-0 border-b border-border"
                            onPointerDown={seekToPointer}
                            onDoubleClick={addChapterOnTrack}
                            title={
                                editable
                                    ? 'Double-click to add a chapter here'
                                    : undefined
                            }
                        >
                            <TimelineChaptersTrack
                                chapters={chapters}
                                duration={duration}
                                pixelsPerSecond={pixelsPerSecond}
                                range={range}
                                editable={editable}
                            />
                        </div>
                        <div
                            className="h-12 shrink-0 border-b border-border"
                            onPointerDown={seekToPointer}
                        >
                            <TimelineTranscriptTrack
                                recordId={recordId}
                                segments={segments}
                                pixelsPerSecond={pixelsPerSecond}
                                range={range}
                                duration={duration}
                                editable={canEdit}
                                onSegmentSaved={onSegmentSaved}
                            />
                        </div>
                        <div
                            className="h-8 shrink-0 border-b border-border"
                            onPointerDown={seekToPointer}
                        >
                            <TimelineScenesTrack
                                shots={shots}
                                scenes={scenes}
                                pixelsPerSecond={pixelsPerSecond}
                                range={range}
                            />
                        </div>
                        <div
                            className="min-h-12 flex-1 border-b border-border"
                            onPointerDown={seekToPointer}
                        >
                            <TimelineWaveformTrack
                                recordId={recordId}
                                available={media.has_waveform}
                                pixelsPerSecond={pixelsPerSecond}
                                viewport={viewport}
                            />
                        </div>
                        <TimelineClipRange pixelsPerSecond={pixelsPerSecond} />
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
