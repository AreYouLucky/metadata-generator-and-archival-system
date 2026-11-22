import { formatTimestamp } from '@/lib/utilities';
import type { VisualFrame } from '@/types/visuals';
import DataTable from './data-table';

type Props = {
    frames: VisualFrame[];
    onSeek: (seconds: number) => void;
};

/**
 * The key frames as a table: each frame with its description, the text on
 * screen, objects and faces counted (never identified). The time plays the
 * video from there.
 */
export default function ReviewVisuals({ frames, onSeek }: Props) {
    return (
        <DataTable
            title="Key frames"
            columns={[
                { label: 'Time', className: 'w-20' },
                { label: 'Frame', className: 'w-36' },
                { label: 'Description' },
                { label: 'Text on screen' },
                { label: 'Objects', className: 'w-40' },
                { label: 'Faces', className: 'w-16 text-right' },
            ]}
            rows={frames.map((frame) => ({
                key: frame.id,
                cells: [
                    <button
                        key="time"
                        type="button"
                        onClick={() => onSeek(frame.time_sec)}
                        className="rounded-sm font-mono text-xs text-brand tabular-nums hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        {formatTimestamp(frame.time_sec)}
                    </button>,
                    <img
                        key="frame"
                        src={frame.url}
                        alt=""
                        loading="lazy"
                        decoding="async"
                        className="aspect-video w-32 rounded-sm bg-black object-cover"
                    />,
                    frame.description,
                    frame.ocr_text && (
                        <span className="whitespace-pre-line">
                            {frame.ocr_text}
                        </span>
                    ),
                    frame.objects && frame.objects.length > 0
                        ? frame.objects.map((object) => object.label).join(', ')
                        : null,
                    frame.face_count,
                ],
            }))}
            empty="No key frames."
        />
    );
}
