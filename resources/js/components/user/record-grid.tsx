import type { CatalogueCard } from '@/types/catalogue';
import RecordCard from './record-card';

type Props = {
    records: CatalogueCard[];
    /** Shown when there are no records. */
    empty: string;
};

/**
 * A responsive grid of public record cards, with an empty state. Columns
 * follow the grid's own width (container queries), so it fills a full-width
 * page and still fits beside the Browse filters. Twelve or twenty-four cards
 * fill 2, 3, 4 and 6 columns evenly.
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
        <div className="@container">
            <ul className="grid gap-x-4 gap-y-8 @xl:grid-cols-2 @3xl:grid-cols-3 @5xl:grid-cols-4 @[100rem]:grid-cols-6 @[100rem]:gap-x-5">
                {records.map((record) => (
                    <li key={record.id}>
                        <RecordCard record={record} />
                    </li>
                ))}
            </ul>
        </div>
    );
}
