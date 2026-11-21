import { useVirtualizer } from '@tanstack/react-virtual';
import { AlignLeft, LocateFixed } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useVideoSync } from '@/hooks/use-video-sync';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type {
    TranscriptSegment as Segment,
    WorkspaceTranscript,
} from '@/types/records';
import EditorPanel from '@/components/encoder/editor-panel';
import TranscriptSearch from './transcript-search';
import TranscriptSegment from './transcript-segment';

type Props = {
    recordId: string;
    transcript: WorkspaceTranscript | null;
    canEdit: boolean;
    transcribing: boolean;
};

/**
 * The transcript, virtualised (a long video has thousands of lines), synced
 * with the player. Auto-scroll pauses while the user scrolls by hand; "Back to
 * current" resumes it.
 */
export default function TranscriptPanel({
    recordId,
    transcript,
    canEdit,
    transcribing,
}: Props) {
    const [segments, setSegments] = useState<Segment[]>(
        transcript?.segments ?? [],
    );
    const [query, setQuery] = useState('');
    const [matchPosition, setMatchPosition] = useState(0);
    const scroller = useRef<HTMLDivElement>(null);
    const activeIndex = useWorkspaceStore((state) => state.activeIndex);
    const followPlayback = useWorkspaceStore((state) => state.followPlayback);
    const paused = useWorkspaceStore((state) => state.paused);
    const setFollowPlayback = useWorkspaceStore(
        (state) => state.setFollowPlayback,
    );
    const seek = useWorkspaceStore((state) => state.seek);

    useEffect(() => setSegments(transcript?.segments ?? []), [transcript]);
    useVideoSync(segments);

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
                className="h-[24rem] overflow-y-auto overscroll-contain xl:h-auto xl:min-h-0 xl:flex-1"
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
                                active={item.index === activeIndex}
                                query={query}
                                canEdit={canEdit}
                                onSaved={(saved) =>
                                    setSegments((current) =>
                                        current.map((segment) =>
                                            segment.id === saved.id
                                                ? { ...segment, ...saved }
                                                : segment,
                                        ),
                                    )
                                }
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
        </EditorPanel>
    );
}
