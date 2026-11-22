import { router } from '@inertiajs/react';
import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { store } from '@/routes/records/audio-export';
import type { AudioExport, AudioExportFormat } from '@/types/records';

/**
 * Make the record's audio downloadable as MP3 or WAV. A finished file comes
 * back ready; otherwise it is made in the background and the workspace hears
 * about it over Reverb. Either way the `audioExports` prop is replaced.
 */
export function useExportAudio(recordId: string) {
    return useMutation({
        mutationFn: async (format: AudioExportFormat) =>
            (
                await http.post<{
                    status: 'ready' | 'processing';
                    exports: AudioExport[];
                }>(store.url(recordId), { format })
            ).data,
        onSuccess: ({ exports }) => router.replaceProp('audioExports', exports),
    });
}
