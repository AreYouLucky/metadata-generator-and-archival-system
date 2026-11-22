import { useWorkspaceStore } from '@/stores/workspace-store';

/**
 * Switches every time display in the workspace between the clock and SMPTE
 * timecode (HH:MM:SS:FF, ";" for drop-frame) from the master's start timecode.
 */
export default function TimecodeToggle() {
    const timeFormat = useWorkspaceStore((state) => state.timeFormat);
    const setTimeFormat = useWorkspaceStore((state) => state.setTimeFormat);
    const timecode = timeFormat === 'timecode';

    return (
        <button
            type="button"
            onClick={() => setTimeFormat(timecode ? 'clock' : 'timecode')}
            aria-pressed={timecode}
            title={
                timecode
                    ? 'Showing timecode; switch to clock'
                    : 'Showing clock; switch to timecode'
            }
            className="rounded px-1.5 py-0.5 font-mono text-[10px] font-semibold tracking-wider text-neutral-400 ring-1 ring-white/15 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none aria-pressed:text-brand aria-pressed:ring-brand/50"
        >
            TC
        </button>
    );
}
