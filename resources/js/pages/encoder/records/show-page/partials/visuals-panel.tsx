import { Info } from 'lucide-react';
import type { ReactNode } from 'react';
import EditorPanel from '@/components/encoder/editor-panel';
import { Skeleton } from '@/components/ui/skeleton';
import type { StageState } from '@/types/records';
import type { Visuals } from '@/types/visuals';
import AddFrameButton from './add-frame-button';
import VisualsFrames from './visuals-frames';
import VisualsScenes from './visuals-scenes';
import VisualsThumbnail from './visuals-thumbnail';

const VISUAL_STAGES = ['scenes', 'frames', 'vision', 'ocr', 'objects'];

type Props = {
    recordId: string;
    /** The inspector tabs, shown in the header's top corner. */
    heading: ReactNode;
    visuals: Visuals;
    stages: StageState[];
    canEdit: boolean;
};

/**
 * PROJECT_SPEC §9 VisualsPanel: the thumbnail ("use current frame" or an
 * upload), the key-frame picker with each frame's description, on-screen
 * text, objects and face count, and the detected shots grouped into scenes.
 */
export default function VisualsPanel({
    recordId,
    heading,
    visuals,
    stages,
    canEdit,
}: Props) {
    const visualStages = stages.filter((stage) =>
        VISUAL_STAGES.includes(stage.stage),
    );
    const running = visualStages.some((stage) =>
        ['pending', 'running'].includes(stage.status),
    );
    const switchedOff =
        visualStages.length > 0 &&
        visualStages.every((stage) => stage.status === 'skipped');
    const failed = visualStages.find((stage) => stage.status === 'failed');

    return (
        <EditorPanel title="Visuals" heading={heading}>
            <div className="flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto p-4">
                <VisualsThumbnail
                    recordId={recordId}
                    thumbnail={visuals.thumbnail}
                    canEdit={canEdit}
                />

                {failed && (
                    <p className="rounded-md bg-red-500/10 p-3 text-xs text-red-300">
                        {failed.label}: {failed.message ?? 'failed'}
                    </p>
                )}

                {visuals.frames.length > 0 ? (
                    <VisualsFrames
                        recordId={recordId}
                        frames={visuals.frames}
                        thumbnail={visuals.thumbnail}
                        canEdit={canEdit}
                    />
                ) : running ? (
                    <div
                        className="grid gap-2"
                        aria-label="Analysing the frames"
                    >
                        <p className="text-xs text-neutral-400">
                            Finding shots and key frames…
                        </p>
                        <div className="grid grid-cols-3 gap-1.5">
                            {Array.from({ length: 6 }, (_, index) => (
                                <Skeleton
                                    key={index}
                                    className="aspect-video animate-pulse rounded-sm"
                                />
                            ))}
                        </div>
                    </div>
                ) : (
                    <div className="grid justify-items-center gap-2 rounded-md border border-dashed border-white/10 p-4 text-center text-sm text-neutral-500">
                        <p>
                            {switchedOff
                                ? 'Visual analysis was turned off for this video.'
                                : 'No key frames yet.'}
                        </p>
                        {canEdit && (
                            <AddFrameButton
                                recordId={recordId}
                                onAdded={() => undefined}
                            />
                        )}
                    </div>
                )}

                {(visuals.shots.length > 0 || visuals.scenes.length > 0) && (
                    <VisualsScenes
                        recordId={recordId}
                        shots={visuals.shots}
                        scenes={visuals.scenes}
                        canEdit={canEdit}
                    />
                )}

                <p className="mt-auto flex gap-2 rounded-md bg-white/5 p-3 text-xs text-neutral-400">
                    <Info className="mt-0.5 size-3.5 shrink-0" aria-hidden />
                    Shots, on-screen text, objects and face counts are worked
                    out on this organisation's server. Small copies of the key
                    frames are sent to OpenAI for the descriptions. Faces are
                    only counted, never recognised.
                </p>
            </div>
        </EditorPanel>
    );
}
