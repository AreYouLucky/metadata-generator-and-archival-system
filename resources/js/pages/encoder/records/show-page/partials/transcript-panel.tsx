import { useVirtualizer } from '@tanstack/react-virtual';
import { AlignLeft, LocateFixed } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useAssignSpeaker } from '@/hooks/use-speakers';
import { useVideoSync } from '@/hooks/use-video-sync';
import { getErrorMessage, speakerName } from '@/lib/utilities';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type {
    TranscriptSegment as Segment,
    Speaker,
    WorkspaceTranscript,
} from '@/types/records';
import EditorPanel from '@/components/encoder/editor-panel';
import DeleteSegmentDialog from './delete-segment-dialog';
import SpeakerManager from './speaker-manager';
import type { SpeakerScope } from './speaker-picker';
import TranscriptSearch from './transcript-search';
import TranscriptSegment from './transcript-segment';

type Props = {
    recordId: string;
    transcript: WorkspaceTranscript | null;
    /** The live lines (the workspace applies edits and deletions to them). */
    segments: Segment[];
    speakers: Speaker[];
    canEdit: boolean;
    transcribing: boolean;
    onSegmentSaved: (
        segment: Pick<Segment, 'id' | 'text' | 'is_edited' | 'speaker_id'>,
    ) => void;
    onSegmentDeleted: (segmentId: number) => void;
    onSpeakerApplied: (segmentIds: number[], speakerId: number | null) => void;
};

/**
 * The transcript, virtualised (a long video has thousands of lines), synced
 * with the player. Auto-scroll pauses while the user scrolls by hand; "Back to
 * current" resumes it. Click a line to jump the video there.
 */
export default function TranscriptPanel({
    recordId,
    transcript,
    segments,
    speakers,
    canEdit,
    transcribing,
    onSegmentSaved,
    onSegmentDeleted,
    onSpeakerApplied,
}: Props) {
    const [query, setQuery] = useState('');
    const [deleting, setDeleting] = useState<Segment | null>(null);
    const [matchPosition, setMatchPosition] = useState(0);
    const scroller = useRef<HTMLDivElement>(null);
    const activeIndex = useWorkspaceStore((state) => state.activeIndex);
    const followPlayback = useWorkspaceStore((state) => state.followPlayback);
    const paused = useWorkspaceStore((state) => state.paused);
    const setFollowPlayback = useWorkspaceStore(
        (state) => state.setFollowPlayback,
    );
    const seek = useWorkspaceStore((state) => state.seek);

    const assignSpeaker = useAssignSpeaker(recordId);

    useVideoSync(segments);

    const applySpeaker = (
        scope: SpeakerScope,
        speakerId: number,
        from: Segment,
    ) => {
        const start =
            scope === 'from-here'
                ? segments.findIndex((segment) => segment.id === from.id)
                : 0;
        const segmentIds = segments
            .slice(Math.max(0, start))
            .filter(
                (segment) =>
                    segment.speaker_id !== speakerId &&
                    (scope !== 'unassigned' || segment.speaker_id === null),
            )
            .map((segment) => segment.id);
        const speaker = speakers.find((item) => item.id === speakerId);
        const name = speaker ? speakerName(speaker) : 'That speaker';

        if (segmentIds.length === 0) {
            toast.info(`${name} is already on those lines.`);

            return;
        }

        assignSpeaker.mutate(
            { segmentIds, speakerId },
            {
                onSuccess: ({ updated }) => {
                    onSpeakerApplied(segmentIds, speakerId);
                    toast.success(
                        `${name} applied to ${updated} ${updated === 1 ? 'line' : 'lines'}.`,
                    );
                },
                onError: (error) =>
                    toast.error(
                        getErrorMessage(
                            error,
                            'The speaker could not be applied.',
                        ),
                    ),
            },
        );
    };

    const matches = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return needle === ''
            ? []
            : segments.flatMap((segment, index) =>
                  segment.text.toLowerCase().includes(needle) ? [index] : [],
              );
    }, [segments, query]);

    const virtualizer = useVirtualizer({
        count: segments.length,
        getScrollElement: () => scroller.current,
        estimateSize: () => 56,
        overscan: 8,
        // Room under the last line, so it scrolls clear of the floating
        // "Back to current" button and the panel's bottom edge.
        paddingEnd: 72,
    });

    useEffect(() => {
        if (followPlayback && activeIndex >= 0 && query === '') {
            virtualizer.scrollToIndex(activeIndex, { align: 'center' });
        }
    }, [activeIndex, followPlayback, query, virtualizer]);

    const goToMatch = (position: number) => {
        if (matches.length === 0) {
            return;
        }

        const wrapped = (position + matches.length) % matches.length;
        const index = matches[wrapped];
        setMatchPosition(wrapped);
        virtualizer.scrollToIndex(index, { align: 'center' });
        seek(segments[index].start);
    };

    const stopFollowing = () => {
        if (followPlayback) {
            setFollowPlayback(false);
        }
    };

    if (!transcript) {
        return (
            <EditorPanel
                title="Transcript"
                icon={<AlignLeft className="size-3.5" aria-hidden />}
            >
                <div className="flex flex-1 flex-col items-center justify-center gap-2 p-6 text-center text-sm text-neutral-400">
                    {transcribing ? (
                        <>
                            <Spinner />
                            The transcript appears here when transcription
                            finishes.
                        </>
                    ) : (
                        'There is no transcript for this record.'
                    )}
                </div>
            </EditorPanel>
        );
    }

    return (
        <EditorPanel
            title={`Transcript · ${segments.length} lines · ${transcript.language}`}
            icon={<AlignLeft className="size-3.5" aria-hidden />}
            className="relative"
            actions={
                (canEdit || speakers.length > 0) && (
                    <SpeakerManager
                        recordId={recordId}
                        speakers={speakers}
                        canEdit={canEdit}
                    />
                )
            }
        >
            <div className="border-b border-white/10 p-2">
                <TranscriptSearch
                    query={query}
                    onQueryChange={(value) => {
                        setQuery(value);
                        setMatchPosition(0);
                    }}
                    matchCount={matches.length}
                    matchPosition={matchPosition}
                    onNext={() => goToMatch(matchPosition + 1)}
                    onPrevious={() => goToMatch(matchPosition - 1)}
                    onSubmit={() => goToMatch(matchPosition)}
                />
            </div>

            <div
                ref={scroller}
                onWheel={stopFollowing}
                onTouchMove={stopFollowing}
                className="min-h-0 flex-1 overflow-y-auto overscroll-contain"
            >
                <ol
                    className="relative w-full"
                    style={{ height: virtualizer.getTotalSize() }}
                >
                    {virtualizer.getVirtualItems().map((item) => (
                        <li
                            key={segments[item.index].id}
                            data-index={item.index}
                            ref={virtualizer.measureElement}
                            className="absolute top-0 left-0 w-full"
                            style={{ transform: `translateY(${item.start}px)` }}
                        >
                            <TranscriptSegment
                                recordId={recordId}
                                segment={segments[item.index]}
                                speakers={speakers}
                                active={item.index === activeIndex}
                                query={query}
                                canEdit={canEdit}
                                onSaved={onSegmentSaved}
                                onDelete={setDeleting}
                                onApplySpeaker={applySpeaker}
                            />
                        </li>
                    ))}
                </ol>
            </div>

            {!followPlayback && !paused && (
                <Button
                    size="sm"
                    className="absolute bottom-3 left-1/2 -translate-x-1/2 shadow-lg"
                    onClick={() => setFollowPlayback(true)}
                >
                    <LocateFixed aria-hidden />
                    Back to current
                </Button>
            )}

            <DeleteSegmentDialog
                recordId={recordId}
                segment={deleting}
                onClose={() => setDeleting(null)}
                onDeleted={onSegmentDeleted}
            />
        </EditorPanel>
    );
}
