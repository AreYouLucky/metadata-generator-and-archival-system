import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import {
    findSegmentIndex,
    formatLineForCopy,
    isTypingTarget,
    speakerName,
} from '@/lib/utilities';
import { useAskMeStore } from '@/stores/ask-me-store';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { Speaker, TranscriptSegment } from '@/types/records';

const JUMP_SECONDS = 5;

/** The DOM id of the transcript search box (focused by "/"). */
export const TRANSCRIPT_SEARCH_ID = 'transcript-search';

/**
 * The workspace keys (PROJECT_SPEC §9), shown in the shortcuts dialog.
 */
export const SHORTCUTS: { keys: string[]; action: string }[] = [
    { keys: ['Space', 'K'], action: 'Play / pause' },
    { keys: ['J', 'L'], action: 'Back / forward 5 seconds' },
    { keys: ['←', '→'], action: 'Previous / next line' },
    { keys: ['C'], action: 'Copy the current line' },
    { keys: ['Shift', 'C'], action: 'Copy the current line with its time' },
    { keys: ['/'], action: 'Search the transcript' },
    { keys: ['E'], action: 'Edit the current line' },
    { keys: ['T'], action: 'Use the current frame as the thumbnail' },
    {
        keys: ['I', 'O'],
        action: 'Mark a clip’s in / out point at the playhead',
    },
    { keys: ['A'], action: 'Open or close “Ask me” (chat with the video)' },
    { keys: ['?'], action: 'Show these shortcuts' },
];

type Options = {
    segments: TranscriptSegment[];
    speakers: Speaker[];
    canEdit: boolean;
    /** Make the frame the player shows the record's thumbnail. */
    onSetThumbnail: () => void;
};

/**
 * Editor keys for the workspace. Ignored while typing, inside a menu or
 * dialog, or with Ctrl / Cmd / Alt held (those belong to the browser). Space
 * on a focused button plays or pauses instead of pressing it again, as in
 * editing software. The listener is attached once; the latest lines are read
 * through a ref, so editing a line doesn't re-attach it.
 */
export function useKeyboardShortcuts(options: Options) {
    const latest = useRef(options);

    useEffect(() => {
        latest.current = options;
    });

    useEffect(() => {
        const currentIndex = (segments: TranscriptSegment[]) => {
            const { activeIndex, player } = useWorkspaceStore.getState();

            return activeIndex >= 0
                ? activeIndex
                : findSegmentIndex(segments, player?.currentTime ?? 0);
        };

        const copyCurrent = (withTimestamp: boolean) => {
            const { segments, speakers } = latest.current;
            const segment = segments[currentIndex(segments)];

            if (!segment) {
                toast.info('There is no line at this point of the video.');

                return;
            }

            const speaker = speakers.find(
                (item) => item.id === segment.speaker_id,
            );
            const text = formatLineForCopy(
                segment,
                speaker ? speakerName(speaker) : null,
                withTimestamp,
            );

            navigator.clipboard
                .writeText(text)
                .then(() => toast.success('Line copied.'))
                .catch(() => toast.error('The line could not be copied.'));
        };

        const onKeyDown = (event: KeyboardEvent) => {
            if (
                event.defaultPrevented ||
                event.ctrlKey ||
                event.metaKey ||
                event.altKey ||
                isTypingTarget(event.target)
            ) {
                return;
            }

            const store = useWorkspaceStore.getState();
            const { player, seek } = store;
            const { segments, canEdit, onSetThumbnail } = latest.current;

            if (event.key === '?') {
                event.preventDefault();
                store.setShortcutsOpen(true);

                return;
            }

            if (event.key === '/') {
                event.preventDefault();
                document.getElementById(TRANSCRIPT_SEARCH_ID)?.focus();

                return;
            }

            // Only the time keys repeat while held.
            if (
                event.repeat &&
                !['ArrowLeft', 'ArrowRight', 'j', 'J', 'l', 'L'].includes(
                    event.key,
                )
            ) {
                return;
            }

            switch (event.key) {
                case ' ':
                case 'k':
                case 'K':
                    event.preventDefault();
                    store.togglePlay();
                    break;
                case 'j':
                case 'J':
                    if (player) {
                        event.preventDefault();
                        seek(player.currentTime - JUMP_SECONDS);
                    }

                    break;
                case 'l':
                case 'L':
                    if (player) {
                        event.preventDefault();
                        seek(
                            Math.min(
                                player.duration || Infinity,
                                player.currentTime + JUMP_SECONDS,
                            ),
                        );
                    }

                    break;
                case 'ArrowLeft':
                case 'ArrowRight': {
                    if (segments.length === 0) {
                        break;
                    }

                    event.preventDefault();
                    const index = currentIndex(segments);
                    const target =
                        event.key === 'ArrowLeft'
                            ? Math.max(0, index - 1)
                            : Math.min(segments.length - 1, index + 1);
                    seek(segments[target].start);
                    break;
                }

                case 'c':
                case 'C':
                    event.preventDefault();
                    copyCurrent(event.shiftKey);
                    break;
                case 'i':
                case 'I':
                case 'o':
                case 'O':
                    if (player) {
                        event.preventDefault();
                        const isIn = event.key.toLowerCase() === 'i';
                        (isIn ? store.setClipIn : store.setClipOut)(
                            player.currentTime,
                        );
                        store.setInspectorTab('clips');
                    }

                    break;
                case 'a':
                case 'A':
                    event.preventDefault();
                    useAskMeStore.getState().toggle();
                    break;
                case 't':
                case 'T':
                    if (canEdit && player) {
                        event.preventDefault();
                        onSetThumbnail();
                    }

                    break;
                case 'e':
                case 'E': {
                    const segment = segments[currentIndex(segments)];

                    if (canEdit && segment) {
                        event.preventDefault();
                        player?.pause();
                        store.setFollowPlayback(true);
                        store.requestEdit(segment.id);
                    }

                    break;
                }
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);
}
