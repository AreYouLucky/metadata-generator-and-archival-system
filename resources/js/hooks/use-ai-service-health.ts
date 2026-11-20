import { useQuery } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { aiHealth } from '@/routes/admin/system';
import type { AiServiceHealth } from '@/types/system';

export const aiServiceHealthKey = ['admin', 'system', 'ai-health'] as const;

/**
 * Health of the Python AI service, as seen from Laravel (signed request).
 */
export function useAiServiceHealth() {
    return useQuery({
        queryKey: aiServiceHealthKey,
        queryFn: async () =>
            (await http.get<AiServiceHealth>(aiHealth.url())).data,
        staleTime: 15_000,
        refetchOnWindowFocus: false,
    });
}
