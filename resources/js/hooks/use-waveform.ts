import { useQuery } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { waveform } from '@/routes/records';
import type { Waveform } from '@/types/records';

/**
 * Waveform peaks for the timeline. They never change once written, so the
 * query is cached for the whole session.
 */
export function useWaveform(recordId: string, enabled: boolean) {
    return useQuery({
        queryKey: ['records', recordId, 'waveform'],
        queryFn: async () =>
            (await http.get<Waveform>(waveform.url(recordId))).data,
        enabled,
        staleTime: Infinity,
        gcTime: 10 * 60_000,
    });
}
