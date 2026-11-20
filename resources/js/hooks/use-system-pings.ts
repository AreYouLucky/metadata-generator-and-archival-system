import { useEcho } from '@laravel/echo-react';
import { useCallback, useRef, useState } from 'react';
import type { PingTarget, SystemPingEvent } from '@/types/system';

export type ReceivedPing = SystemPingEvent & {
    target: PingTarget | null;
    roundTripMs: number | null;
};

const MAX_PINGS = 10;

/**
 * Listens for `system.ping` broadcasts on the private `admin.system` channel
 * and measures the round trip of pings sent from this tab.
 */
export function useSystemPings() {
    const [pings, setPings] = useState<ReceivedPing[]>([]);
    const sent = useRef(
        new Map<string, { target: PingTarget; startedAt: number }>(),
    );

    useEcho<SystemPingEvent>('admin.system', '.system.ping', (event) => {
        const origin = sent.current.get(event.ping_id);
        sent.current.delete(event.ping_id);

        setPings((current) =>
            [
                {
                    ...event,
                    target: origin?.target ?? null,
                    roundTripMs: origin
                        ? Math.round(performance.now() - origin.startedAt)
                        : null,
                },
                ...current,
            ].slice(0, MAX_PINGS),
        );
    });

    const trackSent = useCallback(
        (pingId: string, target: PingTarget, startedAt: number) => {
            sent.current.set(pingId, { target, startedAt });
        },
        [],
    );

    return { pings, trackSent };
}
