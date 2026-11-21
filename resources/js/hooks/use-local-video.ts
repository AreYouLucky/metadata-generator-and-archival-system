import { useEffect, useState } from 'react';

export type LocalVideo = {
    url: string | null;
    duration: number | null;
    thumbnail: string | null;
    /** false when this browser can't decode the file (e.g. ProRes, MXF). */
    playable: boolean | null;
};

const EMPTY: LocalVideo = {
    url: null,
    duration: null,
    thumbnail: null,
    playable: null,
};

/**
 * Preview data for a file chosen on this computer, before it is uploaded: an
 * object URL, the duration, and a thumbnail grabbed from an early frame.
 * Nothing leaves the browser.
 */
export function useLocalVideo(file: File | null): LocalVideo {
    const [video, setVideo] = useState<LocalVideo>(EMPTY);

    useEffect(() => {
        if (!file) {
            setVideo(EMPTY);

            return;
        }

        const url = URL.createObjectURL(file);
        const probe = document.createElement('video');
        let cancelled = false;

        setVideo({ ...EMPTY, url });
        probe.preload = 'metadata';
        probe.muted = true;
        probe.src = url;

        probe.onloadedmetadata = () => {
            if (cancelled) {
                return;
            }

            const duration = Number.isFinite(probe.duration)
                ? probe.duration
                : null;
            setVideo((current) => ({ ...current, duration, playable: true }));
            probe.currentTime = Math.min(1, (duration ?? 0) / 10);
        };

        probe.onseeked = () => {
            if (cancelled || probe.videoWidth === 0) {
                return;
            }

            const canvas = document.createElement('canvas');
            canvas.width = 320;
            canvas.height = Math.round(
                (320 * probe.videoHeight) / probe.videoWidth,
            );
            canvas
                .getContext('2d')
                ?.drawImage(probe, 0, 0, canvas.width, canvas.height);
            const thumbnail = canvas.toDataURL('image/jpeg', 0.7);
            setVideo((current) => ({ ...current, thumbnail }));
        };

        probe.onerror = () => {
            if (!cancelled) {
                setVideo((current) => ({ ...current, playable: false }));
            }
        };

        return () => {
            cancelled = true;
            probe.removeAttribute('src');
            probe.load();
            URL.revokeObjectURL(url);
        };
    }, [file]);

    return video;
}
