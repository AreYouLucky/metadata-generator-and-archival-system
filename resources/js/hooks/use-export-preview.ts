import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { exportMethod } from '@/routes/records';
import type { ExportFormatName } from '@/types/records';

export type ExportChoice = {
    format: ExportFormatName;
    schema: number | null;
    language: string | null;
    timestamps: boolean;
};

/** Query string for an export (download or preview); unused options are left out. */
export function exportQuery(
    choice: ExportChoice,
): Record<string, string | number> {
    const transcriptFormat = ['srt', 'vtt', 'txt', 'csv', 'md'].includes(
        choice.format,
    );

    return {
        format: choice.format,
        ...(choice.format === 'json' && choice.schema !== null
            ? { schema: choice.schema }
            : {}),
        ...(transcriptFormat && choice.language
            ? { language: choice.language }
            : {}),
        ...(choice.format === 'txt' && choice.timestamps
            ? { timestamps: 1 }
            : {}),
    };
}

/**
 * The Export panel's live preview: the export as text, inline and not logged.
 * Only fetched while the panel is open; kept briefly so switching back and
 * forth between formats doesn't refetch. `version` changes when the metadata
 * or transcript change, so the preview follows saved edits.
 */
export function useExportPreview(
    recordId: string,
    choice: ExportChoice,
    version: string,
    enabled: boolean,
) {
    return useQuery({
        queryKey: ['records', recordId, 'export-preview', choice, version],
        queryFn: async () =>
            (
                await http.get<string>(
                    exportMethod.url(recordId, {
                        query: { ...exportQuery(choice), preview: 1 },
                    }),
                    { responseType: 'text', transformResponse: (data) => data },
                )
            ).data,
        enabled,
        staleTime: 20_000,
        placeholderData: keepPreviousData,
    });
}
