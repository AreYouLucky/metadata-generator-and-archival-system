import { toast } from 'sonner';
import { useUpdateSegment } from '@/hooks/use-update-segment';
import { findSegmentIndex, getErrorMessage } from '@/lib/utilities';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { TranscriptSegment } from '@/types/records';
import TimelineTranscriptClip from './timeline-transcript-clip';
import type { Timing } from './timeline-transcript-clip';

type Props = {
    recordId: string;
    segments: TranscriptSegment[];
    pixelsPerSecond: number;
    range: { start: number; end: number };
    duration: number;
    /** Lines can be dragged to fix their timing. */
    editable: boolean;
    onSegmentSaved: (
        saved: Pick<TranscriptSegment, 'id'> & Partial<TranscriptSegment>,
    ) => void;
};

/**
 * Each transcript line as a clip. Only lines inside the visible range are
 * rendered (found by binary search), so long transcripts stay fast. A drag
 * shows the new timing at once and saves it; a failed save puts it back.
 */
export default function TimelineTranscriptTrack({
    recordId,
    segments,
    pixelsPerSecond,
    range,
    duration,
    editable,
    onSegmentSaved,
}: Props) {
    const activeIndex = useWorkspaceStore((state) => state.activeIndex);
    const seek = useWorkspaceStore((state) => state.seek);
    const bumpCaptions = useWorkspaceStore((state) => state.bumpCaptions);
    const update = useUpdateSegment(recordId);
    const visible: number[] = [];

    for (
        let index = Math.max(0, findSegmentIndex(segments, range.start));
        index < segments.length && segments[index].start <= range.end;
        index++
    ) {
        visible.push(index);
    }

    const retime = (segment: TranscriptSegment, timing: Timing) => {
        const before = {
            id: segment.id,
            start: segment.start,
            end: segment.end,
        };
        onSegmentSaved({ id: segment.id, ...timing });
        update.mutate(
            { segmentId: segment.id, timing },
            {
                onSuccess: (saved) => {
                    onSegmentSaved(saved);
                    bumpCaptions();
                },
                onError: (error) => {
                    onSegmentSaved(before);
                    toast.error(
                        getErrorMessage(error, 'The new timing was not saved.'),
                    );
                },
            },
        );
    };

    return (
        <div className="relative h-full">
            {visible.map((index) => (
                <TimelineTranscriptClip
                    key={segments[index].id}
                    segment={segments[index]}
                    active={index === activeIndex}
                    pixelsPerSecond={pixelsPerSecond}
                    editable={editable}
                    bounds={{
                        min: segments[index - 1]?.start ?? 0,
                        max: segments[index + 1]?.start ?? Infinity,
                    }}
                    duration={duration}
                    onSeek={seek}
                    onRetime={retime}
                />
            ))}
        </div>
    );
}
