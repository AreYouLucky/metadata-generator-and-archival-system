import { useCallback } from 'react';
import { formatTimecode } from '@/lib/utilities';
import { useWorkspaceStore } from '@/stores/workspace-store';

/**
 * Formats a position the way the TimecodeToggle says: the usual clock, or
 * SMPTE timecode from the master's start timecode and frame rate.
 */
export function useTimeFormat(fallback: (seconds: number) => string) {
    const timeFormat = useWorkspaceStore((state) => state.timeFormat);
    const frameRate = useWorkspaceStore(
        (state) => state.timecodeBase.frameRate,
    );
    const start = useWorkspaceStore((state) => state.timecodeBase.start);

    return useCallback(
        (seconds: number) =>
            timeFormat === 'timecode'
                ? formatTimecode(seconds, frameRate, start)
                : fallback(seconds),
        [timeFormat, frameRate, start, fallback],
    );
}
