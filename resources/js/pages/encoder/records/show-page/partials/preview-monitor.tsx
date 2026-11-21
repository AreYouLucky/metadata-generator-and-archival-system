import { AlertTriangle } from 'lucide-react';
import { useState } from 'react';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { WorkspaceMedia } from '@/types/records';
import TransportBar from './transport-bar';

/**
 * The program monitor: the master streamed through the authorised media
 * route, letterboxed on black, with editor transport controls below. The
 * <video> element is registered in the workspace store so the transcript,
 * chapters and timeline can seek it.
 */
export default function PreviewMonitor({ media }: { media: WorkspaceMedia }) {
    const setPlayer = useWorkspaceStore((state) => state.setPlayer);
    const setDuration = useWorkspaceStore((state) => state.setDuration);
    const togglePlay = useWorkspaceStore((state) => state.togglePlay);
    const [failed, setFailed] = useState(false);
    const unplayable = media.playable === false || failed;

    return (
        <div className="flex h-full min-h-0 flex-col">
            <div className="relative flex min-h-56 flex-1 items-center justify-center bg-black">
                <video
                    ref={setPlayer}
                    src={media.url}
                    preload="metadata"
                    playsInline
                    onClick={togglePlay}
                    onLoadedMetadata={(event) =>
                        setDuration(
                            event.currentTarget.duration || media.duration || 0,
                        )
                    }
                    onError={() => setFailed(true)}
                    className="aspect-video max-h-full w-full cursor-pointer object-contain xl:absolute xl:inset-0 xl:aspect-auto xl:h-full"
                >
                    Your browser can't play this video.
                </video>
                {unplayable && (
                    <p className="absolute inset-x-4 top-4 flex items-start gap-2 rounded-md bg-amber-500/15 px-3 py-2 text-sm text-amber-100 ring-1 ring-amber-400/30">
                        <AlertTriangle
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden
                        />
                        This master's format can't play in a browser (for
                        example ProRes or MXF). The transcript and metadata
                        still work; a playable access copy comes in a later
                        release.
                    </p>
                )}
            </div>
            <TransportBar fallbackDuration={media.duration ?? 0} />
        </div>
    );
}
