import { useWorkspaceStore } from '@/stores/workspace-store';

type Props = { pixelsPerSecond: number };

/**
 * The range marked for a clip, across every track: a tinted band between
 * the in and out points, or a single line while only one is set.
 */
export default function TimelineClipRange({ pixelsPerSecond }: Props) {
    const { start, end } = useWorkspaceStore((state) => state.clipMarks);

    if (start === null && end === null) {
        return null;
    }

    const left = (start ?? end ?? 0) * pixelsPerSecond;
    const width =
        start !== null && end !== null ? (end - start) * pixelsPerSecond : 0;

    return (
        <div
            className="pointer-events-none absolute top-0 bottom-0 z-[5] border-x-2 border-amber-400/80 bg-amber-400/10"
            style={{ left, width: Math.max(width, 2) }}
            aria-hidden
        />
    );
}
