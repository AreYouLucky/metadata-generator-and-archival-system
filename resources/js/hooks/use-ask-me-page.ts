import { useEffect, useRef } from 'react';
import { useAskMeStore } from '@/stores/ask-me-store';

/**
 * Tell "Ask me" which record this page shows, and how to play it, so "this
 * video" has a meaning and a cited moment of this record plays here instead
 * of opening the page again.
 */
export function useAskMePage(
    recordId: string,
    seek: (seconds: number) => void,
) {
    const latestSeek = useRef(seek);

    useEffect(() => {
        latestSeek.current = seek;
    });

    useEffect(() => {
        const { setPage } = useAskMeStore.getState();
        setPage({ recordId, seek: (seconds) => latestSeek.current(seconds) });

        return () => {
            if (useAskMeStore.getState().page?.recordId === recordId) {
                setPage(null);
            }
        };
    }, [recordId]);
}
