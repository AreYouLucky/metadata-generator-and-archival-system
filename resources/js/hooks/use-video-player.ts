import { useCallback, useEffect, useState } from 'react';

/** Speeds offered by the player's speed menu. */
export const PLAYBACK_RATES = [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2];

const STORAGE_KEY = 'watch-player';

type Preferences = {
    volume: number;
    muted: boolean;
    rate: number;
    captions: string | null;
};

const DEFAULT_PREFERENCES: Preferences = {
    volume: 1,
    muted: false,
    rate: 1,
    captions: null,
};

type Playback = {
    playing: boolean;
    waiting: boolean;
    started: boolean;
    ended: boolean;
    time: number;
    duration: number;
    buffered: number;
};

type FullscreenDocument = Document & {
    webkitFullscreenElement?: Element | null;
    webkitExitFullscreen?: () => Promise<void>;
};

type FullscreenElement = HTMLElement & {
    webkitRequestFullscreen?: () => Promise<void>;
};

type IosVideo = HTMLVideoElement & { webkitEnterFullscreen?: () => void };

/**
 * Read the saved volume, speed and caption language; defaults when storage
 * is unavailable or holds something unexpected.
 */
function readPreferences(): Preferences {
    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}');

        return {
            volume:
                typeof saved.volume === 'number'
                    ? Math.min(1, Math.max(0, saved.volume))
                    : 1,
            muted: saved.muted === true,
            rate: PLAYBACK_RATES.includes(saved.rate) ? saved.rate : 1,
            captions:
                typeof saved.captions === 'string' ? saved.captions : null,
        };
    } catch {
        return DEFAULT_PREFERENCES;
    }
}

/**
 * The text of the cues showing now, without WebVTT tags (voice spans,
 * italics), as plain text: never rendered as HTML.
 */
function activeCueText(track: TextTrack): string | null {
    const cues = Array.from(track.activeCues ?? []) as VTTCue[];
    const text = cues
        .map((cue) => cue.text.replace(/<[^>]+>/g, '').trim())
        .filter(Boolean)
        .join('\n');

    return text || null;
}

/**
 * State and actions for the custom watch-page player: playback, buffering,
 * volume, speed, captions (rendered by the player, not the browser),
 * fullscreen of the whole player and picture-in-picture. Volume, speed and
 * the caption language are remembered between visits.
 */
export function useVideoPlayer(
    video: HTMLVideoElement | null,
    container: HTMLElement | null,
) {
    const [playback, setPlayback] = useState<Playback>({
        playing: false,
        waiting: false,
        started: false,
        ended: false,
        time: 0,
        duration: 0,
        buffered: 0,
    });
    const [preferences, setPreferences] =
        useState<Preferences>(DEFAULT_PREFERENCES);
    const [loaded, setLoaded] = useState(false);
    const [cue, setCue] = useState<string | null>(null);
    const [fullscreen, setFullscreen] = useState(false);
    const [pictureInPicture, setPictureInPicture] = useState(false);
    const [canPictureInPicture, setCanPictureInPicture] = useState(false);

    // Apply the saved preferences once the element exists (client only, so
    // server rendering and the first client render agree).
    useEffect(() => {
        if (!video || loaded) {
            return;
        }

        const saved = readPreferences();
        video.volume = saved.volume;
        video.muted = saved.muted;
        video.playbackRate = saved.rate;
        setPreferences(saved);
        setCanPictureInPicture(document.pictureInPictureEnabled === true);
        setLoaded(true);
    }, [video, loaded]);

    useEffect(() => {
        if (!loaded) {
            return;
        }

        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(preferences));
        } catch {
            // Private mode or full storage: the preference just isn't kept.
        }
    }, [preferences, loaded]);

    useEffect(() => {
        if (!video) {
            return;
        }

        const bufferedEnd = () => {
            const ranges = video.buffered;

            for (let index = 0; index < ranges.length; index++) {
                if (
                    ranges.start(index) <= video.currentTime &&
                    video.currentTime <= ranges.end(index)
                ) {
                    return ranges.end(index);
                }
            }

            return 0;
        };

        const sync = () =>
            setPlayback((current) => ({
                ...current,
                playing: !video.paused,
                ended: video.ended,
                time: video.currentTime,
                duration: Number.isFinite(video.duration) ? video.duration : 0,
                buffered: bufferedEnd(),
            }));

        const onPlay = () => {
            setPlayback((current) => ({ ...current, started: true }));
            sync();
        };
        const onWaiting = () =>
            setPlayback((current) => ({ ...current, waiting: true }));
        const onReady = () =>
            setPlayback((current) => ({ ...current, waiting: false }));
        const onVolume = () =>
            setPreferences((current) => ({
                ...current,
                volume: video.volume,
                muted: video.muted,
            }));
        const onRate = () =>
            setPreferences((current) => ({
                ...current,
                rate: video.playbackRate,
            }));
        const onEnterPip = () => setPictureInPicture(true);
        const onLeavePip = () => setPictureInPicture(false);

        const listeners: [string, () => void][] = [
            ['play', onPlay],
            ['pause', sync],
            ['ended', sync],
            ['timeupdate', sync],
            ['durationchange', sync],
            ['loadedmetadata', sync],
            ['progress', sync],
            ['seeked', sync],
            ['waiting', onWaiting],
            ['playing', onReady],
            ['canplay', onReady],
            ['seeked', onReady],
            ['volumechange', onVolume],
            ['ratechange', onRate],
            ['enterpictureinpicture', onEnterPip],
            ['leavepictureinpicture', onLeavePip],
        ];

        listeners.forEach(([event, listener]) =>
            video.addEventListener(event, listener),
        );
        sync();

        return () =>
            listeners.forEach(([event, listener]) =>
                video.removeEventListener(event, listener),
            );
    }, [video]);

    // Show the chosen language's cues ourselves ("hidden" still fires
    // cuechange), so they sit above the controls and keep the brand styling.
    useEffect(() => {
        if (!video) {
            return;
        }

        let selected: TextTrack | null = null;
        const onCueChange = () => selected && setCue(activeCueText(selected));

        const apply = () => {
            selected?.removeEventListener('cuechange', onCueChange);

            let chosen: TextTrack | null = null;

            for (const track of Array.from(video.textTracks)) {
                const isChosen =
                    preferences.captions !== null &&
                    track.language === preferences.captions;
                track.mode = isChosen ? 'hidden' : 'disabled';

                if (isChosen) {
                    chosen = track;
                }
            }

            selected = chosen;
            chosen?.addEventListener('cuechange', onCueChange);
            setCue(chosen ? activeCueText(chosen) : null);
        };

        apply();
        video.textTracks.addEventListener('addtrack', apply);

        return () => {
            video.textTracks.removeEventListener('addtrack', apply);
            selected?.removeEventListener('cuechange', onCueChange);
        };
    }, [video, preferences.captions]);

    useEffect(() => {
        const onChange = () => {
            const doc = document as FullscreenDocument;
            const element =
                doc.fullscreenElement ?? doc.webkitFullscreenElement ?? null;
            setFullscreen(element !== null && element === container);
        };

        document.addEventListener('fullscreenchange', onChange);
        document.addEventListener('webkitfullscreenchange', onChange);

        return () => {
            document.removeEventListener('fullscreenchange', onChange);
            document.removeEventListener('webkitfullscreenchange', onChange);
        };
    }, [container]);

    const togglePlay = useCallback(() => {
        if (!video) {
            return;
        }

        if (video.paused || video.ended) {
            void video.play().catch(() => undefined);
        } else {
            video.pause();
        }
    }, [video]);

    const seekTo = useCallback(
        (seconds: number) => {
            if (!video) {
                return;
            }

            const end = Number.isFinite(video.duration)
                ? video.duration
                : seconds;
            video.currentTime = Math.min(end, Math.max(0, seconds));
            setPlayback((current) => ({ ...current, time: video.currentTime }));
        },
        [video],
    );

    const skip = useCallback(
        (seconds: number) => {
            if (video) {
                seekTo(video.currentTime + seconds);
            }
        },
        [video, seekTo],
    );

    const setVolume = useCallback(
        (volume: number) => {
            if (!video) {
                return;
            }

            video.volume = Math.min(1, Math.max(0, volume));
            video.muted = video.volume === 0;
        },
        [video],
    );

    const toggleMute = useCallback(() => {
        if (!video) {
            return;
        }

        if (video.muted || video.volume === 0) {
            video.muted = false;
            video.volume = video.volume === 0 ? 0.5 : video.volume;
        } else {
            video.muted = true;
        }
    }, [video]);

    const setRate = useCallback(
        (rate: number) => {
            if (video && PLAYBACK_RATES.includes(rate)) {
                video.playbackRate = rate;
            }
        },
        [video],
    );

    const setCaptions = useCallback(
        (language: string | null) =>
            setPreferences((current) => ({ ...current, captions: language })),
        [],
    );

    const toggleFullscreen = useCallback(() => {
        const doc = document as FullscreenDocument;
        const element = container as FullscreenElement | null;

        if (doc.fullscreenElement ?? doc.webkitFullscreenElement) {
            void (doc.exitFullscreen ?? doc.webkitExitFullscreen)
                ?.call(doc)
                .catch(() => undefined);

            return;
        }

        if (element?.requestFullscreen) {
            void element.requestFullscreen().catch(() => undefined);
        } else if (element?.webkitRequestFullscreen) {
            void element.webkitRequestFullscreen();
        } else {
            // iPhone Safari: only the video element itself can go fullscreen.
            (video as IosVideo | null)?.webkitEnterFullscreen?.();
        }
    }, [container, video]);

    const togglePictureInPicture = useCallback(() => {
        if (!video || !document.pictureInPictureEnabled) {
            return;
        }

        if (document.pictureInPictureElement) {
            void document.exitPictureInPicture().catch(() => undefined);
        } else {
            void video.requestPictureInPicture().catch(() => undefined);
        }
    }, [video]);

    return {
        ...playback,
        ...preferences,
        cue,
        fullscreen,
        pictureInPicture,
        canPictureInPicture,
        togglePlay,
        seekTo,
        skip,
        setVolume,
        toggleMute,
        setRate,
        setCaptions,
        toggleFullscreen,
        togglePictureInPicture,
    };
}

export type VideoPlayer = ReturnType<typeof useVideoPlayer>;
