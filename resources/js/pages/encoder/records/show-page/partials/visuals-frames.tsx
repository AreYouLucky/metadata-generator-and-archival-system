import { ScanFace } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { useTimeFormat } from '@/hooks/use-time-format';
import { useSetThumbnail } from '@/hooks/use-visuals';
import { formatTimestamp, getErrorMessage } from '@/lib/utilities';
import { cn } from '@/lib/utils';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { Thumbnail, VisualFrame } from '@/types/visuals';
import AddFrameButton from './add-frame-button';
import FrameDialog from './frame-dialog';
import FrameTile from './frame-tile';

type Props = {
    recordId: string;
    frames: VisualFrame[];
    thumbnail: Thumbnail | null;
    canEdit: boolean;
};

/**
 * The thumbnail picker: key frames (scene starts and one every few
 * seconds), optionally only those with faces counted. Clicking one jumps
 * the player there and opens it in a dialog with what was found in it, to
 * correct, and "Use as thumbnail".
 */
export default function VisualsFrames({
    recordId,
    frames,
    thumbnail,
    canEdit,
}: Props) {
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const seek = useWorkspaceStore((state) => state.seek);
    const formatTime = useTimeFormat(formatTimestamp);
    const setThumbnail = useSetThumbnail(recordId);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [facesOnly, setFacesOnly] = useState(false);
    const selected = frames.find((frame) => frame.id === selectedId) ?? null;
    const framesWithFaces = useMemo(
        () => frames.filter((frame) => (frame.face_count ?? 0) > 0),
        [frames],
    );
    const withFaces = framesWithFaces.length;
    // Back to every frame if a re-run leaves none with faces.
    const shown = facesOnly && withFaces > 0 ? framesWithFaces : frames;

    const select = (frame: VisualFrame) => {
        setSelectedId(frame.id);
        setDialogOpen(true);
        seek(frame.time_sec);
    };

    const chooseAsThumbnail = (frame: VisualFrame) =>
        setThumbnail.mutate(
            { frameId: frame.id },
            {
                onSuccess: () => toast.success('Thumbnail updated.'),
                onError: (error) =>
                    toast.error(
                        getErrorMessage(
                            error,
                            'The thumbnail could not be set.',
                        ),
                    ),
            },
        );

    return (
        <section aria-labelledby="visuals-frames" className="grid gap-3">
            <div className="flex items-center justify-between gap-2">
                <h3
                    id="visuals-frames"
                    className="text-[11px] font-semibold tracking-wider text-neutral-400 uppercase"
                >
                    Key frames{' '}
                    <span className="text-neutral-500 tabular-nums">
                        {shown.length}
                    </span>
                </h3>
                <div className="flex items-center gap-1.5">
                    {canEdit && (
                        <AddFrameButton
                            recordId={recordId}
                            onAdded={(frame) => {
                                setFacesOnly(false);
                                setSelectedId(frame.id);
                                setDialogOpen(true);
                            }}
                        />
                    )}
                    {withFaces > 0 && (
                        <button
                            type="button"
                            onClick={() => setFacesOnly((value) => !value)}
                            aria-pressed={facesOnly}
                            className={cn(
                                'flex items-center gap-1 rounded-sm px-1.5 py-0.5 text-[11px] text-neutral-400 ring-1 ring-white/10 transition hover:text-white focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                facesOnly && 'bg-white/10 text-white',
                            )}
                        >
                            <ScanFace className="size-3" aria-hidden />
                            People on screen
                            <span className="text-neutral-500 tabular-nums">
                                {withFaces}
                            </span>
                        </button>
                    )}
                </div>
            </div>
            <div className="grid grid-cols-3 gap-1.5">
                {shown.map((frame) => (
                    <FrameTile
                        key={frame.id}
                        frame={frame}
                        label={formatTime(frame.time_sec)}
                        selected={frame.id === selectedId}
                        isThumbnail={thumbnail?.frame_id === frame.id}
                        onSelect={() => select(frame)}
                    />
                ))}
            </div>
            <p className="text-xs text-neutral-500">
                Click a frame to see it large with its description, the text on
                screen, objects and faces counted
                {canEdit ? ', and to correct them' : ''}.
            </p>
            <FrameDialog
                recordId={recordId}
                frame={dialogOpen ? selected : null}
                label={selected ? formatTime(selected.time_sec) : ''}
                isThumbnail={
                    selected !== null && thumbnail?.frame_id === selected.id
                }
                canEdit={canEdit}
                thumbnailPending={setThumbnail.isPending}
                onUseAsThumbnail={() => selected && chooseAsThumbnail(selected)}
                onClose={() => setDialogOpen(false)}
                onRemoved={() => {
                    setDialogOpen(false);
                    setSelectedId(null);
                }}
            />
        </section>
    );
}
