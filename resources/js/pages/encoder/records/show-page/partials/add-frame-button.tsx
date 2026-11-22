import { ImagePlus } from 'lucide-react';
import { toast } from 'sonner';
import { useAddFrame } from '@/hooks/use-visuals';
import { getErrorMessage } from '@/lib/utilities';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { VisualFrame } from '@/types/visuals';

type Props = {
    recordId: string;
    onAdded: (frame: VisualFrame) => void;
};

/**
 * "Add frame": makes the frame the player shows now a key frame (captured
 * from the master on the AI service), then hands it over to be described.
 */
export default function AddFrameButton({ recordId, onAdded }: Props) {
    const player = useWorkspaceStore((state) => state.player);
    const addFrame = useAddFrame(recordId);

    const add = () => {
        if (!player) {
            return;
        }

        addFrame.mutate(
            { timeSec: player.currentTime },
            {
                onSuccess: (frame) => {
                    toast.success('Key frame added. Describe what it shows.');
                    onAdded(frame);
                },
                onError: (error) =>
                    toast.error(
                        getErrorMessage(error, 'The frame could not be added.'),
                    ),
            },
        );
    };

    return (
        <button
            type="button"
            onClick={add}
            disabled={!player || addFrame.isPending}
            title="Add the frame the player shows now as a key frame"
            className="flex items-center gap-1 rounded-sm px-1.5 py-0.5 text-[11px] text-neutral-400 ring-1 ring-white/10 transition hover:text-white focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50"
        >
            <ImagePlus className="size-3" aria-hidden />
            {addFrame.isPending ? 'Adding…' : 'Add frame'}
        </button>
    );
}
