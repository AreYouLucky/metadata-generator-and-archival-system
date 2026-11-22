import { ScanFace, Scissors, Star } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { VisualFrame } from '@/types/visuals';

type Props = {
    frame: VisualFrame;
    label: string;
    selected: boolean;
    isThumbnail: boolean;
    onSelect: () => void;
};

/**
 * One key frame in the picker: the image (loaded when scrolled to), its
 * time, and markers for a scene start, the thumbnail and counted faces.
 */
export default function FrameTile({
    frame,
    label,
    selected,
    isThumbnail,
    onSelect,
}: Props) {
    return (
        <button
            type="button"
            onClick={onSelect}
            aria-pressed={selected}
            aria-label={`Frame at ${label}`}
            className={cn(
                'group relative aspect-video overflow-hidden rounded-sm bg-black ring-1 ring-border transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                selected && 'ring-2 ring-brand',
            )}
        >
            <img
                src={frame.url}
                alt=""
                loading="lazy"
                decoding="async"
                className="size-full object-cover transition group-hover:opacity-90"
            />
            <span className="absolute bottom-0 left-0 rounded-tr-sm bg-black/75 px-1 font-mono text-[10px] text-white tabular-nums">
                {label}
            </span>
            <span className="absolute top-0.5 right-0.5 flex gap-0.5">
                {isThumbnail && (
                    <Star
                        className="size-3.5 fill-amber-400 text-amber-400 drop-shadow"
                        aria-label="Thumbnail"
                    />
                )}
                {frame.is_scene_start && (
                    <Scissors
                        className="size-3.5 text-white drop-shadow"
                        aria-label="Shot starts here"
                    />
                )}
                {(frame.face_count ?? 0) > 0 && (
                    <ScanFace
                        className="size-3.5 text-white drop-shadow"
                        aria-label={`${frame.face_count} faces counted`}
                    />
                )}
            </span>
        </button>
    );
}
