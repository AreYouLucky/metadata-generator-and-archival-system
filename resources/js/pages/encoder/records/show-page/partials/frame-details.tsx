import { Copy, Save, Star, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useClipboard } from '@/hooks/use-clipboard';
import { useDeleteFrame, useUpdateFrame } from '@/hooks/use-visuals';
import { getErrorMessage } from '@/lib/utilities';
import type { VisualFrame } from '@/types/visuals';

type Props = {
    recordId: string;
    frame: VisualFrame;
    isThumbnail: boolean;
    canEdit: boolean;
    thumbnailPending: boolean;
    onUseAsThumbnail: () => void;
    /** Called once the frame has been removed. */
    onRemoved: () => void;
};

/**
 * What the analysis found in one frame. The description and the text on
 * screen can be corrected (they feed metadata generation) and the frame
 * removed; objects and the face count come from the detectors (faces
 * counted only, never identified).
 */
export default function FrameDetails({
    recordId,
    frame,
    isThumbnail,
    canEdit,
    thumbnailPending,
    onUseAsThumbnail,
    onRemoved,
}: Props) {
    const [, copy] = useClipboard();
    const updateFrame = useUpdateFrame(recordId);
    const deleteFrame = useDeleteFrame(recordId);
    const [confirmingRemove, setConfirmingRemove] = useState(false);
    const [description, setDescription] = useState(frame.description ?? '');
    const [ocrText, setOcrText] = useState(frame.ocr_text ?? '');
    const changed =
        description.trim() !== (frame.description ?? '') ||
        ocrText.trim() !== (frame.ocr_text ?? '');

    const copyText = async () => {
        if (ocrText && (await copy(ocrText))) {
            toast.success('Text copied.');
        } else {
            toast.error('Copying is not available in this browser.');
        }
    };

    const save = () =>
        updateFrame.mutate(
            { frameId: frame.id, description, ocr_text: ocrText },
            {
                onSuccess: (saved) => {
                    setDescription(saved.description ?? '');
                    setOcrText(saved.ocr_text ?? '');
                    toast.success('Frame saved.');
                },
                onError: (error) =>
                    toast.error(
                        getErrorMessage(error, 'The frame could not be saved.'),
                    ),
            },
        );

    const remove = () =>
        deleteFrame.mutate(frame.id, {
            onSuccess: () => {
                toast.success('Key frame removed.');
                onRemoved();
            },
            onError: (error) =>
                toast.error(
                    getErrorMessage(error, 'The frame could not be removed.'),
                ),
        });

    return (
        <div className="grid gap-4 text-sm">
            <div className="grid gap-1.5">
                <Label htmlFor={`frame-${frame.id}-description`}>
                    Description
                </Label>
                {canEdit ? (
                    <Textarea
                        id={`frame-${frame.id}-description`}
                        value={description}
                        onChange={(event) => setDescription(event.target.value)}
                        maxLength={2000}
                        rows={3}
                        placeholder="What is in this frame"
                    />
                ) : (
                    <p id={`frame-${frame.id}-description`}>
                        {frame.description ?? '—'}
                    </p>
                )}
            </div>

            <div className="grid gap-1.5">
                <div className="flex items-center justify-between">
                    <Label htmlFor={`frame-${frame.id}-text`}>
                        Text on screen
                    </Label>
                    {ocrText && (
                        <button
                            type="button"
                            onClick={() => void copyText()}
                            className="inline-flex items-center gap-1 rounded-sm text-xs text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <Copy className="size-3" aria-hidden />
                            Copy
                        </button>
                    )}
                </div>
                {canEdit ? (
                    <Textarea
                        id={`frame-${frame.id}-text`}
                        value={ocrText}
                        onChange={(event) => setOcrText(event.target.value)}
                        maxLength={5000}
                        rows={3}
                        placeholder="No text on screen"
                    />
                ) : (
                    <p
                        id={`frame-${frame.id}-text`}
                        className="whitespace-pre-line"
                    >
                        {frame.ocr_text ?? '—'}
                    </p>
                )}
            </div>

            <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2">
                <dt className="text-xs text-muted-foreground">Objects</dt>
                <dd className="flex flex-wrap gap-1">
                    {frame.objects && frame.objects.length > 0
                        ? frame.objects.map((object) => (
                              <Badge key={object.label} variant="secondary">
                                  {object.label}
                                  <span className="text-muted-foreground tabular-nums">
                                      {Math.round(object.confidence * 100)}%
                                  </span>
                              </Badge>
                          ))
                        : '—'}
                </dd>
                <dt className="text-xs text-muted-foreground">Faces</dt>
                <dd>
                    {frame.face_count === null
                        ? '—'
                        : `${frame.face_count} counted (no one is identified)`}
                </dd>
            </dl>

            {canEdit &&
                (confirmingRemove ? (
                    <div
                        role="alert"
                        className="flex flex-wrap items-center justify-end gap-2 rounded-md bg-destructive/10 p-2"
                    >
                        <p className="mr-auto text-sm">
                            Remove this key frame and its image?
                        </p>
                        <Button
                            variant="ghost"
                            onClick={() => setConfirmingRemove(false)}
                            disabled={deleteFrame.isPending}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={remove}
                            disabled={deleteFrame.isPending}
                        >
                            <Trash2 aria-hidden />
                            {deleteFrame.isPending ? 'Removing…' : 'Remove'}
                        </Button>
                    </div>
                ) : (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="ghost"
                            className="mr-auto text-destructive hover:text-destructive"
                            onClick={() => setConfirmingRemove(true)}
                        >
                            <Trash2 aria-hidden />
                            Remove frame
                        </Button>
                        <Button
                            variant="outline"
                            onClick={onUseAsThumbnail}
                            disabled={isThumbnail || thumbnailPending}
                        >
                            <Star aria-hidden />
                            {isThumbnail ? 'Thumbnail' : 'Use as thumbnail'}
                        </Button>
                        <Button
                            onClick={save}
                            disabled={!changed || updateFrame.isPending}
                        >
                            <Save aria-hidden />
                            {updateFrame.isPending ? 'Saving…' : 'Save'}
                        </Button>
                    </div>
                ))}
        </div>
    );
}
