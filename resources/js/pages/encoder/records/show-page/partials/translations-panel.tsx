import type { ReactNode } from 'react';
import EditorPanel from '@/components/encoder/editor-panel';
import type { Translation, TranslationLanguage } from '@/types/records';
import AddTranslationForm from './add-translation-form';
import TranslationRow from './translation-row';

type Props = {
    recordId: string;
    heading: ReactNode;
    translations: Translation[];
    languages: TranslationLanguage[];
    originalLanguage: string | null;
    canEdit: boolean;
    hasTranscript: boolean;
    onStarted: (translation: Translation) => void;
};

/**
 * Translations of the transcript, one per language: add one, watch it run,
 * then show it as captions, read it or download its subtitles.
 */
export default function TranslationsPanel({
    recordId,
    heading,
    translations,
    languages,
    originalLanguage,
    canEdit,
    hasTranscript,
    onStarted,
}: Props) {
    const taken = new Set<string | null>([
        ...translations.map((translation) => translation.language),
        originalLanguage,
    ]);
    const available = languages.filter((language) => !taken.has(language.code));

    return (
        <EditorPanel title="Translations" heading={heading}>
            <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-5">
                {!hasTranscript ? (
                    <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        Translations can be made once the transcript is ready.
                    </p>
                ) : (
                    <>
                        {canEdit && available.length > 0 && (
                            <AddTranslationForm
                                recordId={recordId}
                                languages={available}
                                onStarted={onStarted}
                            />
                        )}
                        {translations.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No translations yet.
                            </p>
                        ) : (
                            <ul className="grid gap-2">
                                {translations.map((translation) => (
                                    <TranslationRow
                                        key={translation.id}
                                        recordId={recordId}
                                        translation={translation}
                                        canEdit={canEdit}
                                        onStarted={onStarted}
                                    />
                                ))}
                            </ul>
                        )}
                    </>
                )}
            </div>
        </EditorPanel>
    );
}
