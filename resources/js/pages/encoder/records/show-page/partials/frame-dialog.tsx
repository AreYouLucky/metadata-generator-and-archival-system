import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { VisualFrame } from '@/types/visuals';
import FrameDetails from './frame-details';

type Props = {
    recordId: string;
    /** The frame to show; null closes the dialog. */
    frame: VisualFrame | null;
    label: string;
    isThumbnail: boolean;
    canEdit: boolean;
    thumbnailPending: boolean;
    onUseAsThumbnail: () => void;
    onClose: () => void;
    onRemoved: () => void;
};

/**
 * A clicked key frame, large, with what was found in it and (for editors)
 * the description and on-screen text to correct.
 */
export default function FrameDialog({
    recordId,
    frame,
    label,
    isThumbnail,
    canEdit,
    thumbnailPending,
    onUseAsThumbnail,
    onClose,
    onRemoved,
}: Props) {
    return (
        <Dialog
            open={frame !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                {frame && (
                    <>
                        <DialogHeader>
                            <DialogTitle className="font-mono tabular-nums">
                                Frame at {label}
                                {frame.is_scene_start && ' · shot start'}
                            </DialogTitle>
                            <DialogDescription>
                                {canEdit
                                    ? 'Correct the description or the text on screen (used the next time metadata is generated), or remove the frame.'
                                    : 'What the analysis found in this frame.'}
                            </DialogDescription>
                        </DialogHeader>
                        <img
                            src={frame.url}
                            alt={frame.description ?? `Frame at ${label}`}
                            className="aspect-video w-full rounded-md bg-black object-contain"
                        />
                        {/* Keyed so a different frame starts from its own text. */}
                        <FrameDetails
                            key={frame.id}
                            recordId={recordId}
                            frame={frame}
                            isThumbnail={isThumbnail}
                            canEdit={canEdit}
                            thumbnailPending={thumbnailPending}
                            onUseAsThumbnail={onUseAsThumbnail}
                            onRemoved={onRemoved}
                        />
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
