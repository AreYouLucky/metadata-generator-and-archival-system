import { useVirtualizer } from '@tanstack/react-virtual';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { useTimeFormat } from '@/hooks/use-time-format';
import { formatTimestamp } from '@/lib/utilities';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { Scene, Shot } from '@/types/visuals';
import SceneGroupForm from './scene-group-form';
import SceneRow from './scene-row';
import ShotRow from './shot-row';

const SHOT_ROW_HEIGHT = 28;

type Props = {
    recordId: string;
    shots: Shot[];
    scenes: Scene[];
    canEdit: boolean;
};

/**
 * Detected shots and the scenes made from them. Click a shot to jump there
 * and start a selection, click another to extend it, then name the run as a
 * scene. The shot list is virtualised (a long video can have thousands).
 */
export default function VisualsScenes({
    recordId,
    shots,
    scenes,
    canEdit,
}: Props) {
    const [range, setRange] = useState<[number, number] | null>(null);
    const [naming, setNaming] = useState(false);
    const seek = useWorkspaceStore((state) => state.seek);
    const formatTime = useTimeFormat(formatTimestamp);
    const scroller = useRef<HTMLDivElement>(null);
    const virtualizer = useVirtualizer({
        count: shots.length,
        getScrollElement: () => scroller.current,
        estimateSize: () => SHOT_ROW_HEIGHT,
        overscan: 8,
    });

    const pick = (index: number) => {
        seek(shots[index].start);
        setNaming(false);
        setRange((current) =>
            current === null || current[0] !== current[1]
                ? [index, index]
                : [Math.min(current[0], index), Math.max(current[0], index)],
        );
    };

    const selected = range
        ? {
              first: shots[range[0]],
              last: shots[range[1]],
              count: range[1] - range[0] + 1,
          }
        : null;

    return (
        <section aria-labelledby="visuals-scenes" className="grid gap-3">
            <h3
                id="visuals-scenes"
                className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
            >
                Shots and scenes{' '}
                <span className="text-muted-foreground tabular-nums">
                    {shots.length}
                </span>
            </h3>

            {shots.length === 0 ? (
                <p className="text-xs text-muted-foreground">
                    No shots were detected yet.
                </p>
            ) : (
                <>
                    <div
                        ref={scroller}
                        className="max-h-48 overflow-y-auto rounded-md ring-1 ring-border"
                    >
                        <div
                            className="relative"
                            style={{ height: virtualizer.getTotalSize() }}
                        >
                            {virtualizer.getVirtualItems().map((item) => (
                                <div
                                    key={shots[item.index].id}
                                    className="absolute inset-x-0"
                                    style={{
                                        top: item.start,
                                        height: SHOT_ROW_HEIGHT,
                                    }}
                                >
                                    <ShotRow
                                        shot={shots[item.index]}
                                        number={item.index + 1}
                                        label={formatTime(
                                            shots[item.index].start,
                                        )}
                                        selected={
                                            range !== null &&
                                            item.index >= range[0] &&
                                            item.index <= range[1]
                                        }
                                        onClick={() => pick(item.index)}
                                    />
                                </div>
                            ))}
                        </div>
                    </div>

                    {canEdit && selected && !naming && (
                        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            <span>
                                {selected.count === 1
                                    ? `Shot at ${formatTime(selected.first.start)}. Click another shot to select a run.`
                                    : `${selected.count} shots, ${formatTime(selected.first.start)} to ${formatTime(selected.last.end)}.`}
                            </span>
                            <Button size="sm" onClick={() => setNaming(true)}>
                                Make a scene
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => setRange(null)}
                            >
                                Clear
                            </Button>
                        </div>
                    )}

                    {canEdit && selected && naming && (
                        <SceneGroupForm
                            recordId={recordId}
                            start={selected.first.start}
                            end={selected.last.end}
                            shotCount={selected.count}
                            onDone={() => {
                                setNaming(false);
                                setRange(null);
                            }}
                        />
                    )}
                </>
            )}

            {scenes.length > 0 && (
                <ul className="grid gap-0.5" aria-label="Scenes">
                    {scenes.map((scene) => (
                        <li key={scene.id}>
                            <SceneRow
                                recordId={recordId}
                                scene={scene}
                                label={`${formatTime(scene.start)} – ${formatTime(scene.end)}`}
                                canEdit={canEdit}
                            />
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
