import { ScanFace } from 'lucide-react';
import { formatTimestamp } from '@/lib/utilities';
import { cn } from '@/lib/utils';
import type { FaceSummary } from '@/types/reviews';

type Props = {
    faces: FaceSummary;
    onSeek: (seconds: number) => void;
};

/**
 * The privacy check before approving: how many key frames show people
 * (faces are only counted, never recognised), the first of them to jump
 * to, and whether release forms are on file.
 */
export default function ReviewFaces({ faces, onSeek }: Props) {
    const consentOnFile = faces.release_consent === 'available';
    const more = faces.frames_with_faces - faces.frames.length;

    if (faces.frames_with_faces === 0) {
        return (
            <section className="flex gap-2 rounded-xl border p-4 text-sm text-muted-foreground">
                <ScanFace className="mt-0.5 size-4 shrink-0" aria-hidden />
                No faces were counted in its {faces.counted_frames} key frames.
            </section>
        );
    }

    return (
        <section
            aria-labelledby="review-faces"
            className={cn(
                'grid gap-3 rounded-xl border p-4',
                !consentOnFile &&
                    'border-amber-500/40 bg-amber-500/5 dark:border-amber-400/30',
            )}
        >
            <h2
                id="review-faces"
                className={cn(
                    'flex items-center gap-2 text-sm font-medium',
                    !consentOnFile && 'text-amber-700 dark:text-amber-300',
                )}
            >
                <ScanFace className="size-4 shrink-0" aria-hidden />
                People on screen
            </h2>
            <p className="text-sm text-muted-foreground">
                Faces were counted in {faces.frames_with_faces} of{' '}
                {faces.counted_frames} key frames.{' '}
                {consentOnFile
                    ? 'Release forms are on file.'
                    : 'No release forms are on file: check that the people shown agreed to be published, or keep it off the public catalogue.'}
            </p>
            <div className="grid grid-cols-3 gap-1.5">
                {faces.frames.map((frame) => (
                    <button
                        key={frame.id}
                        type="button"
                        onClick={() => onSeek(frame.time_sec)}
                        aria-label={`Play from ${formatTimestamp(frame.time_sec)} (${frame.face_count} ${frame.face_count === 1 ? 'face' : 'faces'})`}
                        className="group relative aspect-video overflow-hidden rounded-sm bg-black ring-1 ring-border focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <img
                            src={frame.url}
                            alt=""
                            loading="lazy"
                            decoding="async"
                            className="size-full object-cover transition group-hover:opacity-90"
                        />
                        <span className="absolute bottom-0 left-0 rounded-tr-sm bg-black/75 px-1 font-mono text-[10px] text-white tabular-nums">
                            {formatTimestamp(frame.time_sec)}
                        </span>
                        <span className="absolute top-0.5 right-0.5 flex items-center gap-0.5 rounded-sm bg-black/75 px-1 text-[10px] text-white tabular-nums">
                            <ScanFace className="size-3" aria-hidden />
                            {frame.face_count}
                        </span>
                    </button>
                ))}
            </div>
            {more > 0 && (
                <p className="text-xs text-muted-foreground">
                    And {more} more later in the video.
                </p>
            )}
        </section>
    );
}
