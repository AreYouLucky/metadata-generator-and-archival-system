import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { formatEnumLabel, languageName } from '@/lib/utilities';
import type { Translation } from '@/types/records';
import DataTable from './data-table';
import TranslationLinesTable from './translation-lines-table';

type Props = {
    recordId: string;
    translations: Translation[];
    onSeek: (seconds: number) => void;
};

/**
 * The transcript's translations as a table; "View" opens a finished one's
 * lines below.
 */
export default function ReviewTranslations({
    recordId,
    translations,
    onSeek,
}: Props) {
    const [open, setOpen] = useState<string | null>(null);

    return (
        <div className="grid gap-4">
            <DataTable
                title="Translations"
                columns={[
                    { label: 'Language' },
                    { label: 'Status', className: 'w-28' },
                    { label: 'Lines', className: 'w-20' },
                    { label: 'Edited', className: 'w-20' },
                    { label: '', className: 'w-24' },
                ]}
                rows={translations.map((translation) => ({
                    key: translation.id,
                    cells: [
                        <span key="language" className="grid">
                            {languageName(translation.language)}
                            {translation.error && (
                                <span className="text-xs text-destructive">
                                    {translation.error}
                                </span>
                            )}
                        </span>,
                        formatEnumLabel(translation.status),
                        translation.line_count,
                        translation.edited_count,
                        translation.status === 'done' ? (
                            <Button
                                key="view"
                                size="sm"
                                variant={
                                    open === translation.language
                                        ? 'secondary'
                                        : 'outline'
                                }
                                onClick={() =>
                                    setOpen(
                                        open === translation.language
                                            ? null
                                            : translation.language,
                                    )
                                }
                            >
                                {open === translation.language
                                    ? 'Hide'
                                    : 'View'}
                            </Button>
                        ) : null,
                    ],
                }))}
                empty="No translations."
            />
            {open && (
                <TranslationLinesTable
                    recordId={recordId}
                    language={open}
                    onSeek={onSeek}
                />
            )}
        </div>
    );
}
