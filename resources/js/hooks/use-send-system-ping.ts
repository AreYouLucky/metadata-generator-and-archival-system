import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { ping } from '@/routes/admin/system';
import type { PingTarget, SystemPingAccepted } from '@/types/system';

/**
 * Start a test ping. The result arrives later as a `system.ping` broadcast
 * (see useSystemPings), so nothing needs invalidating here.
 */
export function useSendSystemPing() {
    return useMutation({
        mutationFn: async (target: PingTarget) =>
            (await http.post<SystemPingAccepted>(ping.url(), { target })).data,
    });
}
