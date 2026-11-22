import type { CatalogueCard } from '@/types/catalogue';
import RecordCard from './record-card';

type Props = {
    records: CatalogueCard[];
    /** Shown when there are no records. */
    empty: string;
};

/**
 * A responsive grid of public record cards, with an empty state.
 */
export default function RecordGrid({ records, empty }: Props) {
    if (records.length === 0) {
        return (
            <div className="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                {empty}
            </div>
        );
    }

    return (
        <ul className="grid gap-x-4 gap-y-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {records.map((record) => (
                <li key={record.id}>
                    <RecordCard record={record} />
                </li>
            ))}
        </ul>
    );
}
