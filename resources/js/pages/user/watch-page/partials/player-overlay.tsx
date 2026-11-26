import { Play, RotateCcw } from 'lucide-react';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type Props = {
    playing: boolean;
    waiting: boolean;
    ended: boolean;
    onPlay: () => void;
};

/**
 * The centre of the player: a large play (or replay) button while paused,
 * and a spinner while the video buffers.
 */
export default function PlayerOverlay({
    playing,
    waiting,
    ended,
    onPlay,
}: Props) {
    if (waiting && playing) {
        return (
            <div className="pointer-events-none absolute inset-0 grid place-items-center">
                <Spinner className="size-12 text-white drop-shadow-lg" />
            </div>
        );
    }

    if (playing) {
        return null;
    }

    const Icon = ended ? RotateCcw : Play;

    return (
        <div className="pointer-events-none absolute inset-0 grid place-items-center">
            <button
                type="button"
                onClick={onPlay}
                aria-label={ended ? 'Replay' : 'Play'}
                className={cn(
                    'pointer-events-auto grid size-16 place-items-center rounded-full bg-sky-600 text-white shadow-xl ring-4 ring-white/20 transition hover:scale-105 hover:bg-sky-500 focus-visible:ring-sky-300 focus-visible:outline-none sm:size-20',
                )}
            >
                <Icon
                    className={cn(
                        'size-7 sm:size-9',
                        !ended && 'translate-x-0.5 fill-current',
                    )}
                />
            </button>
        </div>
    );
}
