import { Volume1, Volume2, VolumeX } from 'lucide-react';

type Props = {
    volume: number;
    muted: boolean;
    onVolumeChange: (volume: number) => void;
    onToggleMute: () => void;
};

/**
 * Mute button with a volume slider that slides out on hover or focus.
 */
export default function PlayerVolume({
    volume,
    muted,
    onVolumeChange,
    onToggleMute,
}: Props) {
    const level = muted ? 0 : volume;
    const Icon = level === 0 ? VolumeX : level < 0.5 ? Volume1 : Volume2;

    return (
        <div className="group/volume flex items-center">
            <button
                type="button"
                onClick={onToggleMute}
                aria-label={level === 0 ? 'Unmute (m)' : 'Mute (m)'}
                title={level === 0 ? 'Unmute (m)' : 'Mute (m)'}
                className="inline-flex size-9 items-center justify-center rounded-full text-white transition hover:bg-white/15 focus-visible:ring-2 focus-visible:ring-sky-400 focus-visible:outline-none"
            >
                <Icon className="size-5" />
            </button>
            <div className="w-0 overflow-hidden transition-[width] duration-200 group-focus-within/volume:w-24 group-hover/volume:w-24">
                <input
                    type="range"
                    min={0}
                    max={1}
                    step={0.05}
                    value={level}
                    onChange={(event) =>
                        onVolumeChange(Number(event.target.value))
                    }
                    aria-label="Volume"
                    aria-valuetext={`${Math.round(level * 100)}%`}
                    className="mx-2 h-1 w-20 cursor-pointer accent-sky-500"
                />
            </div>
        </div>
    );
}
