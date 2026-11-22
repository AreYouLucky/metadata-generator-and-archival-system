import {
    formatDateTime,
    formatEnumLabel,
    formatTimestamp,
} from '@/lib/utilities';
import type { ActiveMetadata } from '@/types/records';
import DataTable from './data-table';
import FieldTable from './field-table';

type Props = {
    metadata: ActiveMetadata | null;
    onSeek: (seconds: number) => void;
};

/**
 * The active AI generation as the encoder left it: titles, description,
 * summary, tags, keywords, hashtags, chapters, quotes, tone and content
 * flags, each as a table.
 */
export default function ReviewMetadata({ metadata, onSeek }: Props) {
    if (metadata === null) {
        return (
            <p className="rounded-xl border p-6 text-center text-sm text-muted-foreground">
                No metadata has been generated yet.
            </p>
        );
    }

    const { payload } = metadata;
    const flags = payload.contentFlags
        ? [
              ...(payload.contentFlags.profanity ? ['Profanity'] : []),
              ...payload.contentFlags.sensitiveTopics.map(formatEnumLabel),
          ]
        : null;
    const timeButton = (seconds: number) => (
        <button
            type="button"
            onClick={() => onSeek(seconds)}
            className="text-brand tabular-nums hover:underline"
        >
            {formatTimestamp(seconds)}
        </button>
    );

    return (
        <div className="grid gap-4">
            <FieldTable
                title="Generation"
                rows={[
                    ['Platform preset', formatEnumLabel(metadata.preset)],
                    ['Model', metadata.model],
                    ['Custom instructions', metadata.custom_prompt],
                    ['Generated', formatDateTime(metadata.created_at)],
                ]}
            />

            <FieldTable
                title="Content"
                rows={[
                    [
                        'Chosen title',
                        payload.selectedTitle ?? payload.titles[0] ?? null,
                    ],
                    ['Description', payload.description],
                    ['Summary', payload.summary],
                    ['Tags', payload.tags.join(', ')],
                    ['Keywords', payload.keywords.join(', ')],
                    ['Hashtags', payload.hashtags.join(' ')],
                    [
                        'Tone',
                        payload.tone ? formatEnumLabel(payload.tone) : null,
                    ],
                    [
                        'Content flags',
                        flags === null
                            ? null
                            : flags.length === 0
                              ? 'None'
                              : flags.join(', '),
                    ],
                ]}
            />

            <DataTable
                title="Title options"
                columns={[
                    { label: '#', className: 'w-12' },
                    { label: 'Title' },
                    { label: 'Chosen', className: 'w-24' },
                ]}
                rows={payload.titles.map((title, index) => ({
                    key: index,
                    cells: [
                        index + 1,
                        title,
                        title === (payload.selectedTitle ?? payload.titles[0])
                            ? 'Yes'
                            : '',
                    ],
                }))}
                empty="No title options."
            />

            <DataTable
                title="Chapters"
                columns={[
                    { label: 'Start', className: 'w-24' },
                    { label: 'Title' },
                ]}
                rows={payload.chapters.map((chapter, index) => ({
                    key: index,
                    cells: [timeButton(chapter.start), chapter.title],
                }))}
                empty="No chapters."
            />

            <DataTable
                title="Key quotes"
                columns={[
                    { label: 'Time', className: 'w-24' },
                    { label: 'Speaker', className: 'w-36' },
                    { label: 'Quote' },
                ]}
                rows={payload.quotes.map((quote, index) => ({
                    key: index,
                    cells: [timeButton(quote.start), quote.speaker, quote.text],
                }))}
                empty="No quotes."
            />
        </div>
    );
}
