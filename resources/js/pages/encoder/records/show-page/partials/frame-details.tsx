import { Copy, Star } from 'lucide-react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import type { VisualFrame } from '@/types/visuals';

type Props = {
    frame: VisualFrame;
    label: string;
    isThumbnail: boolean;
    canEdit: boolean;
    pending: boolean;
    onUseAsThumbnail: () => void;
};

/**
 * What the analysis found in one frame: a description, the text on screen,
 * objects, and how many faces were counted (counted only, never identified).
 */
export default function FrameDetails({
    frame,
    label,
    isThumbnail,
    canEdit,
    pending,
    onUseAsThumbnail,
}: Props) {
    const [, copy] = useClipboard();

    const copyText = async () => {
        if (frame.ocr_text && (await copy(frame.ocr_text))) {
            toast.success('Text copied.');
        } else {
            toast.error('Copying is not available in this browser.');
        }
    };

    return (
        <div className="grid gap-3 rounded-md bg-white/5 p-3 text-sm">
            <div className="flex items-center justify-between gap-2">
                <span className="font-mono text-xs text-neutral-400 tabular-nums">
                    {label}
                    {frame.is_scene_start && ' · shot start'}
                </span>
                {canEdit && (
                    <Button
                        size="sm"
                        variant="secondary"
                        onClick={onUseAsThumbnail}
                        disabled={isThumbnail || pending}
                    >
                        <Star aria-hidden />
                        {isThumbnail ? 'Thumbnail' : 'Use as thumbnail'}
                    </Button>
                )}
            </div>

            <dl className="grid gap-2.5">
                <div className="grid gap-0.5">
                    <dt className="text-xs text-neutral-500">Description</dt>
                    <dd>{frame.description ?? '—'}</dd>
                </div>
                <div className="grid gap-0.5">
                    <dt className="flex items-center justify-between text-xs text-neutral-500">
                        Text on screen
                        {frame.ocr_text && (
                            <button
                                type="button"
                                onClick={() => void copyText()}
                                className="inline-flex items-center gap-1 rounded-sm hover:text-neutral-300 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <Copy className="size-3" aria-hidden />
                                Copy
                            </button>
                        )}
                    </dt>
                    <dd className="whitespace-pre-line">
                        {frame.ocr_text ?? '—'}
                    </dd>
                </div>
                <div className="grid gap-1">
                    <dt className="text-xs text-neutral-500">Objects</dt>
                    <dd className="flex flex-wrap gap-1">
                        {frame.objects && frame.objects.length > 0
                            ? frame.objects.map((object) => (
                                  <Badge key={object.label} variant="secondary">
                                      {object.label}
                                      <span className="text-neutral-500 tabular-nums">
                                          {Math.round(object.confidence * 100)}%
                                      </span>
                                  </Badge>
                              ))
                            : '—'}
                    </dd>
                </div>
                <div className="grid gap-0.5">
                    <dt className="text-xs text-neutral-500">Faces</dt>
                    <dd>
                        {frame.face_count === null
                            ? '—'
                            : `${frame.face_count} counted (no one is identified)`}
                    </dd>
                </div>
            </dl>
        </div>
    );
}
