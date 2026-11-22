import { formatTimestamp } from '@/lib/utilities';
import type { WatchFrame } from '@/types/catalogue';

type Props = {
    frames: WatchFrame[];
    onSeek: (seconds: number) => void;
};

/**
 * What is on screen through the video, as a table: each key frame with its
 * description, the text shown and the objects seen. A click on the time or
 * the frame plays from there.
 */
export default function WatchFrames({ frames, onSeek }: Props) {
    return (
        <div className="overflow-x-auto rounded-xl border">
            <table className="w-full text-sm">
                <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                    <tr>
                        <th scope="col" className="w-20 px-4 py-2 font-medium">
                            Time
                        </th>
                        <th scope="col" className="w-40 px-4 py-2 font-medium">
                            Frame
                        </th>
                        <th scope="col" className="px-4 py-2 font-medium">
                            What it shows
                        </th>
                        <th scope="col" className="px-4 py-2 font-medium">
                            Text on screen
                        </th>
                        <th scope="col" className="w-44 px-4 py-2 font-medium">
                            Objects
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {frames.map((frame) => (
                        <tr key={frame.id} className="align-top">
                            <td className="px-4 py-2">
                                <button
                                    type="button"
                                    onClick={() => onSeek(frame.time_sec)}
                                    className="rounded-sm font-mono text-xs text-brand tabular-nums hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    {formatTimestamp(frame.time_sec)}
                                </button>
                            </td>
                            <td className="px-4 py-2">
                                <button
                                    type="button"
                                    onClick={() => onSeek(frame.time_sec)}
                                    aria-label={`Play from ${formatTimestamp(frame.time_sec)}`}
                                    className="block rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <img
                                        src={frame.url}
                                        alt={frame.description ?? ''}
                                        loading="lazy"
                                        decoding="async"
                                        className="aspect-video w-36 rounded-sm bg-black object-cover"
                                    />
                                </button>
                            </td>
                            <td className="px-4 py-2">
                                {frame.description ?? '—'}
                            </td>
                            <td className="px-4 py-2 whitespace-pre-line">
                                {frame.ocr_text ?? '—'}
                            </td>
                            <td className="px-4 py-2 text-muted-foreground">
                                {frame.objects.length > 0
                                    ? frame.objects.join(', ')
                                    : '—'}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
