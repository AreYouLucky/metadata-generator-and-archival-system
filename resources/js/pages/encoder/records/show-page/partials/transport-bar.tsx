import {
    Maximize,
    Pause,
    Play,
    SkipBack,
    SkipForward,
    Volume2,
    VolumeX,
} from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { formatClock } from '@/lib/utilities';
import { useWorkspaceStore } from '@/stores/workspace-store';

const JUMP_SECONDS = 5;

/**
 * Transport under the monitor: back/forward 5 s, play/pause, the current
 * time against the duration, mute and full screen.
 */
export default function TransportBar({
    fallbackDuration,
}: {
    fallbackDuration: number;
}) {
    const player = useWorkspaceStore((state) => state.player);
    const paused = useWorkspaceStore((state) => state.paused);
    const currentTime = useWorkspaceStore((state) => state.currentTime);
    const duration =
        useWorkspaceStore((state) => state.duration) || fallbackDuration;
    const togglePlay = useWorkspaceStore((state) => state.togglePlay);
    const seek = useWorkspaceStore((state) => state.seek);
    const [muted, setMuted] = useState(false);

    const toggleMute = () => {
        if (player) {
            player.muted = !player.muted;
            setMuted(player.muted);
        }
    };

    return (
        <div className="flex h-12 shrink-0 items-center gap-1 border-t border-white/10 bg-neutral-900 px-2">
            <span className="w-28 font-mono text-xs text-brand tabular-nums">
                {formatClock(currentTime)}
            </span>
            <div className="flex flex-1 items-center justify-center gap-1">
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    onClick={() => seek(currentTime - JUMP_SECONDS)}
                    disabled={!player}
                    aria-label={`Back ${JUMP_SECONDS} seconds`}
                >
                    <SkipBack aria-hidden />
                </Button>
                <Button
                    size="icon"
                    className="size-9 rounded-full"
                    onClick={togglePlay}
                    disabled={!player}
                    aria-label={paused ? 'Play' : 'Pause'}
                >
                    {paused ? <Play aria-hidden /> : <Pause aria-hidden />}
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    onClick={() =>
                        seek(Math.min(duration, currentTime + JUMP_SECONDS))
                    }
                    disabled={!player}
                    aria-label={`Forward ${JUMP_SECONDS} seconds`}
                >
                    <SkipForward aria-hidden />
                </Button>
            </div>
            <span className="w-28 text-right font-mono text-xs text-neutral-400 tabular-nums">
                {formatClock(duration)}
            </span>
            <Button
                variant="ghost"
                size="icon"
                className="size-8"
                onClick={toggleMute}
                disabled={!player}
                aria-label={muted ? 'Unmute' : 'Mute'}
            >
                {muted ? <VolumeX aria-hidden /> : <Volume2 aria-hidden />}
            </Button>
            <Button
                variant="ghost"
                size="icon"
                className="size-8"
                onClick={() => void player?.requestFullscreen()}
                disabled={!player}
                aria-label="Full screen"
            >
                <Maximize aria-hidden />
            </Button>
        </div>
    );
}
