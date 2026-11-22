import { AlertTriangle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { captions } from '@/routes/records';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type {
    Contributor,
    ContributorRoleOption,
    WorkspaceMedia,
} from '@/types/records';
import FrameTextTool from './frame-text-tool';
import TransportBar from './transport-bar';

type Props = {
    recordId: string;
    media: WorkspaceMedia;
    contributors: Contributor[];
    contributorRoles: ContributorRoleOption[];
    canEdit: boolean;
    /** Transcript languages with captions (original first). */
    captionLanguages: string[];
};

/**
 * The program monitor: the master streamed through the authorised media
 * route, letterboxed on black, with editor transport controls below. The
 * <video> element is registered in the workspace store so the transcript,
 * chapters and timeline can seek it. While paused, "Copy text" reads the
 * text shown in the frame.
 */
export default function PreviewMonitor({
    recordId,
    media,
    contributors,
    contributorRoles,
    canEdit,
    captionLanguages,
}: Props) {
    const player = useWorkspaceStore((state) => state.player);
    const captionLanguage = useWorkspaceStore((state) => state.captionLanguage);
    const captionsVersion = useWorkspaceStore((state) => state.captionsVersion);
    const setPlayer = useWorkspaceStore((state) => state.setPlayer);
    const setDuration = useWorkspaceStore((state) => state.setDuration);
    const togglePlay = useWorkspaceStore((state) => state.togglePlay);
    const [failed, setFailed] = useState(false);
    const unplayable = media.playable === false || failed;

    // Show only the chosen caption track (tracks reload when lines change).
    useEffect(() => {
        if (!player) {
            return;
        }

        for (const track of Array.from(player.textTracks)) {
            track.mode =
                track.language === captionLanguage ? 'showing' : 'disabled';
        }
    }, [player, captionLanguage, captionLanguages, captionsVersion]);

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
                    {captionLanguages.map((language) => (
                        <track
                            key={`${language}-${captionsVersion}`}
                            kind="subtitles"
                            srcLang={language}
                            label={language}
                            src={captions.url(
                                { record: recordId, language },
                                { query: { v: captionsVersion } },
                            )}
                        />
                    ))}
                    Your browser can't play this video.
                </video>
                {!unplayable && (
                    <FrameTextTool
                        recordId={recordId}
                        contributors={contributors}
                        roles={contributorRoles}
                        canEdit={canEdit}
                    />
                )}
                {unplayable && (
                    <p className="absolute inset-x-4 top-4 flex items-start gap-2 rounded-md bg-amber-500/15 px-3 py-2 text-sm text-amber-100 ring-1 ring-amber-400/30">
                        <AlertTriangle
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden
                        />
                        This master's format can't play in a browser (for
                        example ProRes or MXF). A playable access copy is made
                        after the transcript and metadata; it plays here once it
                        is ready.
                    </p>
                )}
            </div>
            <TransportBar
                fallbackDuration={media.duration ?? 0}
                captionLanguages={captionLanguages}
            />
        </div>
    );
}
