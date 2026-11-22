import { useEffect, useRef } from 'react';

/**
 * Play from `?t=` (seconds) once the page's player is ready: links from "Ask
 * me" citations open a record at the cited moment. `player` is the page's
 * <video> element (null until it mounts).
 */
export function useStartTime(player: HTMLVideoElement | null) {
    const done = useRef(false);

    useEffect(() => {
        const start = Number(
            new URLSearchParams(window.location.search).get('t'),
        );

        if (done.current || !player || !Number.isFinite(start) || start <= 0) {
            return;
        }

        const seek = () => {
            done.current = true;
            player.currentTime = start;
        };

        if (player.readyState >= 1) {
            seek();

            return;
        }

        player.addEventListener('loadedmetadata', seek, { once: true });

        return () => player.removeEventListener('loadedmetadata', seek);
    }, [player]);
}
