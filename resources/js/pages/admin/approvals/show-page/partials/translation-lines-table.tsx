import { Spinner } from '@/components/ui/spinner';
import { useTranslationLines } from '@/hooks/use-translations';
import { formatTimestamp, languageName } from '@/lib/utilities';
import DataTable from './data-table';

type Props = {
    recordId: string;
    language: string;
    onSeek: (seconds: number) => void;
};

/**
 * One finished translation's lines as a table (loaded when opened).
 */
export default function TranslationLinesTable({
    recordId,
    language,
    onSeek,
}: Props) {
    const lines = useTranslationLines(recordId, language, true);

    if (lines.isPending) {
        return (
            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                <Spinner /> Loading the {languageName(language)} lines…
            </p>
        );
    }

    if (lines.isError) {
        return (
            <p className="text-sm text-destructive">
                The {languageName(language)} lines could not be loaded.
            </p>
        );
    }

    return (
        <div className="max-h-[32rem] overflow-auto">
            <DataTable
                title={`${languageName(language)} translation`}
                columns={[
                    { label: 'Time', className: 'w-24' },
                    { label: 'Text' },
                    { label: 'Edited', className: 'w-20' },
                ]}
                rows={lines.data.segments.map((segment) => ({
                    key: segment.id,
                    cells: [
                        <button
                            key="time"
                            type="button"
                            onClick={() => onSeek(segment.start)}
                            className="text-brand tabular-nums hover:underline"
                        >
                            {formatTimestamp(segment.start)}
                        </button>,
                        segment.text,
                        segment.is_edited ? 'Yes' : 'No',
                    ],
                }))}
                empty="No lines."
            />
        </div>
    );
}
