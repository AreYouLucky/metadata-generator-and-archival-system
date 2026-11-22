import { Camera, ImageIcon, Upload } from 'lucide-react';
import { useRef } from 'react';
import type { ChangeEvent } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTimeFormat } from '@/hooks/use-time-format';
import { useSetThumbnail } from '@/hooks/use-visuals';
import { formatTimestamp, getErrorMessage } from '@/lib/utilities';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { Thumbnail } from '@/types/visuals';

/** Same limit as the server (StoreProjectRequest::thumbnailRules). */
const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

type Props = {
    recordId: string;
    thumbnail: Thumbnail | null;
    canEdit: boolean;
};

/**
 * The record's thumbnail, shown in the catalogue: "Use current frame" takes
 * the paused frame from the master (at full quality, on the server), or an
 * image can be uploaded. Key frames below can also be chosen.
 */
export default function VisualsThumbnail({
    recordId,
    thumbnail,
    canEdit,
}: Props) {
    const setThumbnail = useSetThumbnail(recordId);
    const player = useWorkspaceStore((state) => state.player);
    const formatTime = useTimeFormat(formatTimestamp);
    const fileInput = useRef<HTMLInputElement>(null);

    const notify = {
        onSuccess: () => toast.success('Thumbnail updated.'),
        onError: (error: unknown) =>
            toast.error(
                getErrorMessage(error, 'The thumbnail could not be set.'),
            ),
    };

    const takeCurrentFrame = () => {
        if (player) {
            setThumbnail.mutate({ timeSec: player.currentTime }, notify);
        }
    };

    const upload = (event: ChangeEvent<HTMLInputElement>) => {
        const image = event.target.files?.[0];
        event.target.value = '';

        if (!image) {
            return;
        }

        if (image.size > MAX_IMAGE_BYTES) {
            toast.error('The thumbnail must be 5 MB or smaller.');

            return;
        }

        setThumbnail.mutate({ image }, notify);
    };

    return (
        <section aria-labelledby="visuals-thumbnail" className="grid gap-3">
            <h3
                id="visuals-thumbnail"
                className="text-[11px] font-semibold tracking-wider text-neutral-400 uppercase"
            >
                Thumbnail
            </h3>
            <div className="flex gap-3">
                <div className="relative aspect-video w-40 shrink-0 overflow-hidden rounded-md bg-black ring-1 ring-white/10">
                    {thumbnail ? (
                        <img
                            src={thumbnail.url}
                            alt="The record's thumbnail"
                            className="size-full object-contain"
                        />
                    ) : (
                        <div className="flex size-full items-center justify-center text-neutral-600">
                            <ImageIcon className="size-6" aria-hidden />
                        </div>
                    )}
                    {setThumbnail.isPending && (
                        <div className="absolute inset-0 flex items-center justify-center bg-black/60">
                            <Spinner />
                        </div>
                    )}
                </div>
                <div className="grid min-w-0 content-start gap-2 text-xs text-neutral-400">
                    <p>
                        {thumbnail === null
                            ? 'None yet. One is picked from the key frames after analysis.'
                            : thumbnail.time_sec === null
                              ? 'An uploaded image.'
                              : `Taken at ${formatTime(thumbnail.time_sec)}.`}
                    </p>
                    {canEdit && (
                        <div className="flex flex-wrap gap-2">
                            <Button
                                size="sm"
                                variant="secondary"
                                onClick={takeCurrentFrame}
                                disabled={!player || setThumbnail.isPending}
                                title="Use the frame the player shows now (T)"
                            >
                                <Camera aria-hidden />
                                Use current frame
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => fileInput.current?.click()}
                                disabled={setThumbnail.isPending}
                            >
                                <Upload aria-hidden />
                                Upload
                            </Button>
                            <input
                                ref={fileInput}
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                className="hidden"
                                onChange={upload}
                                aria-label="Upload a thumbnail image"
                            />
                        </div>
                    )}
                </div>
            </div>
        </section>
    );
}
