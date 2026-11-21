import { useEffect } from 'react';
import { findSegmentIndex } from '@/lib/utilities';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { TranscriptSegment } from '@/types/records';

/**
 * Keeps the workspace store in step with the player: current time, paused
 * state and the active transcript segment. Uses `timeupdate` plus
 * requestAnimationFrame while playing, so the highlight moves smoothly.
 * Store updates are skipped when nothing changed, to avoid re-renders.
 */
export function useVideoSync(segments: TranscriptSegment[]) {
    const player = useWorkspaceStore((state) => state.player);

    useEffect(() => {
        if (!player) {
            return;
        }

        let frame = 0;

        const sync = () => {
            const {
                currentTime,
                paused,
                activeIndex,
                setPlayback,
                setActiveIndex,
            } = useWorkspaceStore.getState();
            const time = player.currentTime;

            if (
                Math.abs(time - currentTime) >= 0.05 ||
                paused !== player.paused
            ) {
                setPlayback(time, player.paused);
            }

            const index = findSegmentIndex(segments, time);

            if (index !== activeIndex) {
                setActiveIndex(index);
            }
        };

        const loop = () => {
            sync();
            frame = requestAnimationFrame(loop);
        };

        const onPlay = () => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(loop);
        };

        const onPause = () => {
            cancelAnimationFrame(frame);
            sync();
        };

        player.addEventListener('play', onPlay);
        player.addEventListener('pause', onPause);
        player.addEventListener('seeked', sync);
        player.addEventListener('timeupdate', sync);
        sync();

        if (!player.paused) {
            onPlay();
        }

        return () => {
            cancelAnimationFrame(frame);
            player.removeEventListener('play', onPlay);
            player.removeEventListener('pause', onPause);
            player.removeEventListener('seeked', sync);
            player.removeEventListener('timeupdate', sync);
        };
    }, [player, segments]);
}
