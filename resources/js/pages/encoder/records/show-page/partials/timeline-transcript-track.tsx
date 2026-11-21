import { findSegmentIndex } from '@/lib/utilities';
import { cn } from '@/lib/utils';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { TranscriptSegment } from '@/types/records';

type Props = {
    segments: TranscriptSegment[];
    pixelsPerSecond: number;
    range: { start: number; end: number };
};

/**
 * Each transcript line as a clip. Only lines inside the visible range are
 * rendered (found by binary search), so long transcripts stay fast.
 */
export default function TimelineTranscriptTrack({
    segments,
    pixelsPerSecond,
    range,
}: Props) {
    const activeIndex = useWorkspaceStore((state) => state.activeIndex);
    const seek = useWorkspaceStore((state) => state.seek);
    const visible: number[] = [];

    for (
        let index = Math.max(0, findSegmentIndex(segments, range.start));
        index < segments.length && segments[index].start <= range.end;
        index++
    ) {
        visible.push(index);
    }

    return (
        <div className="relative h-full">
            {visible.map((index) => {
                const segment = segments[index];
                const width = Math.max(
                    2,
                    (segment.end - segment.start) * pixelsPerSecond - 1,
                );

                return (
                    <button
                        key={segment.id}
                        type="button"
                        tabIndex={-1}
                        title={segment.text}
                        onPointerDown={(event) => {
                            event.stopPropagation();
                            seek(segment.start);
                        }}
                        className={cn(
                            'absolute top-1.5 bottom-1.5 overflow-hidden rounded-sm border px-1 text-left text-[10px] leading-tight text-white/90',
                            index === activeIndex
                                ? 'border-brand bg-brand/60'
                                : 'border-emerald-400/20 bg-emerald-700/40 hover:bg-emerald-600/50',
                        )}
                        style={{
                            left: segment.start * pixelsPerSecond,
                            width,
                        }}
                    >
                        {width > 36 && (
                            <span className="line-clamp-2">{segment.text}</span>
                        )}
                    </button>
                );
            })}
        </div>
    );
}
