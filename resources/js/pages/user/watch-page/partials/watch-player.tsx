import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { KeyboardEvent, PointerEvent } from 'react';
import { PLAYBACK_RATES, useVideoPlayer } from '@/hooks/use-video-player';
import { isTypingTarget, languageName } from '@/lib/utilities';
import { cn } from '@/lib/utils';
import { captions as captionsRoute } from '@/routes/catalogue';
import type { WatchCaption, WatchFrame } from '@/types/catalogue';
import PlayerControls from './player-controls';
import PlayerOverlay from './player-overlay';

type Props = {
    recordId: string;
    title: string;
    mediaUrl: string;
    poster: string | null;
    captions: WatchCaption[];
    chapters: { start: number; title: string }[];
    frames: WatchFrame[];
    currentChapter: string | null;
    /** Receives the <video> element (null on unmount) for page-level seeking. */
    videoRef: (element: HTMLVideoElement | null) => void;
    onTimeUpdate: (seconds: number) => void;
};

const HIDE_CONTROLS_AFTER_MS = 2500;

/**
 * The public player, with our own controls instead of the browser's: a
 * timeline with chapter breaks and key-frame previews, skip, volume, speed,
 * captions drawn above the controls, picture-in-picture and fullscreen of
 * the whole player. The controls fade while playing and come back on any
 * movement. With the player focused: Space/K play, J/L ±10s, ←/→ ±5s, ↑/↓
 * volume, M mute, C captions, F fullscreen, 0–9 jump, < > speed. No
 * download button and no right-click menu on the video.
 */
export default function WatchPlayer({
    recordId,
    title,
    mediaUrl,
    poster,
    captions,
    chapters,
    frames,
    currentChapter,
    videoRef,
    onTimeUpdate,
}: Props) {
    const [video, setVideo] = useState<HTMLVideoElement | null>(null);
    const [container, setContainer] = useState<HTMLDivElement | null>(null);
    const [active, setActive] = useState(true);
    const [scrubbing, setScrubbing] = useState(false);
    const [menuOpen, setMenuOpen] = useState(false);
    const hideTimer = useRef<number | undefined>(undefined);
    const lastCaptions = useRef<string | null>(null);
    const latestVideoRef = useRef(videoRef);

    const player = useVideoPlayer(video, container);

    useEffect(() => {
        latestVideoRef.current = videoRef;
    });

    useEffect(() => () => window.clearTimeout(hideTimer.current), []);

    useEffect(() => {
        if (player.captions) {
            lastCaptions.current = player.captions;
        }
    }, [player.captions]);

    const attachVideo = useCallback((element: HTMLVideoElement | null) => {
        setVideo(element);
        latestVideoRef.current(element);
    }, []);

    const previews = useMemo(
        () =>
            frames
                .map((frame) => ({ time_sec: frame.time_sec, url: frame.url }))
                .sort((a, b) => a.time_sec - b.time_sec),
        [frames],
    );

    const showControls = useCallback(() => {
        setActive(true);
        window.clearTimeout(hideTimer.current);
        hideTimer.current = window.setTimeout(
            () => setActive(false),
            HIDE_CONTROLS_AFTER_MS,
        );
    }, []);

    const visible = !player.playing || active || scrubbing || menuOpen;

    const toggleCaptions = () => {
        if (player.captions) {
            player.setCaptions(null);

            return;
        }

        const original = captions.find((caption) => caption.is_original);
        player.setCaptions(
            lastCaptions.current ??
                original?.language ??
                captions[0]?.language ??
                null,
        );
    };

    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        if (
            event.ctrlKey ||
            event.metaKey ||
            event.altKey ||
            isTypingTarget(event.target)
        ) {
            return;
        }

        const onButton =
            event.target instanceof HTMLElement &&
            event.target.closest('button') !== null;
        const step = (rate: number) => {
            const index = PLAYBACK_RATES.indexOf(player.rate);
            const next = Math.min(
                PLAYBACK_RATES.length - 1,
                Math.max(0, index + rate),
            );
            player.setRate(PLAYBACK_RATES[next]);
        };

        switch (event.key) {
            case ' ':
                if (onButton) {
                    return;
                }

                player.togglePlay();
                break;
            case 'k':
            case 'K':
                player.togglePlay();
                break;
            case 'j':
            case 'J':
                player.skip(-10);
                break;
            case 'l':
            case 'L':
                player.skip(10);
                break;
            case 'ArrowLeft':
                player.skip(-5);
                break;
            case 'ArrowRight':
                player.skip(5);
                break;
            case 'ArrowUp':
                player.setVolume((player.muted ? 0 : player.volume) + 0.1);
                break;
            case 'ArrowDown':
                player.setVolume((player.muted ? 0 : player.volume) - 0.1);
                break;
            case 'm':
            case 'M':
                player.toggleMute();
                break;
            case 'f':
            case 'F':
                player.toggleFullscreen();
                break;
            case 'c':
            case 'C':
                if (captions.length === 0) {
                    return;
                }

                toggleCaptions();
                break;
            case '<':
                step(-1);
                break;
            case '>':
                step(1);
                break;
            default:
                if (/^[0-9]$/.test(event.key) && player.duration > 0) {
                    player.seekTo((Number(event.key) / 10) * player.duration);
                    break;
                }

                return;
        }

        event.preventDefault();
        event.stopPropagation();
        showControls();
    };

    const onVideoPointerUp = (event: PointerEvent<HTMLVideoElement>) => {
        // A tap on a touch screen first brings the controls back.
        if (event.pointerType !== 'mouse' && !visible) {
            showControls();

            return;
        }

        player.togglePlay();
        showControls();
    };

    return (
        <div
            ref={setContainer}
            tabIndex={0}
            role="region"
            aria-label={`Video player: ${title}`}
            onKeyDown={onKeyDown}
            onPointerMove={showControls}
            onFocus={showControls}
            className={cn(
                'relative isolate aspect-video max-h-[75svh] w-full overflow-hidden rounded-xl bg-black ring-1 ring-black/5 select-none focus-visible:ring-2 focus-visible:ring-sky-400 focus-visible:outline-none',
                player.fullscreen && 'max-h-none rounded-none ring-0',
                !visible && 'cursor-none',
            )}
        >
            <video
                ref={attachVideo}
                src={mediaUrl}
                poster={poster ?? undefined}
                preload="metadata"
                playsInline
                onTimeUpdate={(event) =>
                    onTimeUpdate(event.currentTarget.currentTime)
                }
                onPointerUp={onVideoPointerUp}
                onDoubleClick={player.toggleFullscreen}
                onContextMenu={(event) => event.preventDefault()}
                className="absolute inset-0 size-full object-contain"
            >
                {captions.map((caption) => (
                    <track
                        key={caption.language}
                        kind="captions"
                        src={captionsRoute.url([recordId, caption.language])}
                        srcLang={caption.language}
                        label={languageName(caption.language)}
                    />
                ))}
            </video>

            {player.fullscreen && (
                <div
                    className={cn(
                        'pointer-events-none absolute inset-x-0 top-0 bg-linear-to-b from-black/75 to-transparent px-6 pt-5 pb-16 transition-opacity duration-300',
                        visible ? 'opacity-100' : 'opacity-0',
                    )}
                >
                    <p className="truncate text-lg font-medium text-white">
                        {title}
                    </p>
                </div>
            )}

            <PlayerOverlay
                playing={player.playing}
                waiting={player.waiting}
                ended={player.ended}
                onPlay={player.togglePlay}
            />

            {player.cue && (
                <div
                    className={cn(
                        'pointer-events-none absolute inset-x-0 flex justify-center px-6 transition-[bottom] duration-300',
                        visible ? 'bottom-24 sm:bottom-28' : 'bottom-5 sm:bottom-8',
                    )}
                >
                    <p
                        className={cn(
                            'max-w-[90%] rounded-md bg-black/75 px-3 py-1 text-center text-sm leading-snug whitespace-pre-line text-white sm:text-lg',
                            player.fullscreen && 'text-xl sm:text-3xl',
                        )}
                    >
                        {player.cue}
                    </p>
                </div>
            )}

            <div
                className={cn(
                    'absolute inset-x-0 bottom-0 transition-opacity duration-300',
                    visible ? 'opacity-100' : 'pointer-events-none opacity-0',
                )}
            >
                <PlayerControls
                    player={player}
                    video={video}
                    captions={captions}
                    chapters={chapters}
                    previews={previews}
                    chapterTitle={currentChapter}
                    onScrubbingChange={setScrubbing}
                    onMenuOpenChange={setMenuOpen}
                />
            </div>
        </div>
    );
}
