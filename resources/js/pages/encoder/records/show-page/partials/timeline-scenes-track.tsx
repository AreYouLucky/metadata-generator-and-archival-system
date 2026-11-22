import type { PointerEvent as ReactPointerEvent } from 'react';
import { cn } from '@/lib/utils';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { Scene, Shot } from '@/types/visuals';

type Props = {
    shots: Shot[];
    scenes: Scene[];
    pixelsPerSecond: number;
    range: { start: number; end: number };
};

/**
 * Scenes on the timeline: named scenes as labelled clips along the top, the
 * detected shots as alternating strips underneath (a cut wherever the shade
 * changes). Only what is on screen is drawn. Click a scene to jump to it.
 */
export default function TimelineScenesTrack({
    shots,
    scenes,
    pixelsPerSecond,
    range,
}: Props) {
    const seek = useWorkspaceStore((state) => state.seek);
    const visible = <T extends Shot>(item: T) =>
        item.end >= range.start && item.start <= range.end;

    const jump = (event: ReactPointerEvent, seconds: number) => {
        event.stopPropagation();
        seek(seconds);
    };

    return (
        <div className="relative h-full">
            {scenes.filter(visible).map((scene) => {
                const width = Math.max(
                    2,
                    (scene.end - scene.start) * pixelsPerSecond - 2,
                );

                return (
                    <button
                        key={scene.id}
                        type="button"
                        tabIndex={-1}
                        title={scene.label ?? undefined}
                        onPointerDown={(event) => jump(event, scene.start)}
                        className="absolute top-0.5 h-4 overflow-hidden rounded-sm border-l-2 border-white/70 bg-yellow-700/90 px-1 text-left text-[10px] leading-4 whitespace-nowrap text-white select-none hover:bg-yellow-600/90"
                        style={{ left: scene.start * pixelsPerSecond, width }}
                    >
                        {width > 30 && scene.label}
                    </button>
                );
            })}
            {shots.map((shot, index) =>
                visible(shot) ? (
                    <div
                        key={shot.id}
                        aria-hidden
                        className={cn(
                            'absolute bottom-1 h-2.5 border-l border-black/60',
                            index % 2 === 0
                                ? 'bg-neutral-500/70'
                                : 'bg-neutral-400/50',
                        )}
                        style={{
                            left: shot.start * pixelsPerSecond,
                            width: Math.max(
                                1,
                                (shot.end - shot.start) * pixelsPerSecond,
                            ),
                        }}
                    />
                ) : null,
            )}
        </div>
    );
}
