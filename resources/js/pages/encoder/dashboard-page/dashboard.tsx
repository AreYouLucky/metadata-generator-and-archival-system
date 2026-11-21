import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import SimplePagination from '@/components/all/simple-pagination';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/records';
import type { Paginated } from '@/types/pagination';
import type { RecordFilters, RecordListItem } from '@/types/records';
import RecordsFilters from './partials/records-filters';
import RecordsTable from './partials/records-table';

type Props = {
    records: Paginated<RecordListItem>;
    filters: RecordFilters;
    showCreator: boolean;
};

export default function Dashboard({ records, filters, showCreator }: Props) {
    const filtered =
        filters.q !== '' ||
        filters.processing_status !== null ||
        filters.review_status !== null;

    return (
        <>
            <Head title="Records" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Records"
                        description={
                            showCreator
                                ? 'Every record in the archive, from all encoders.'
                                : 'The videos you have added to the archive.'
                        }
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus aria-hidden />
                            New record
                        </Link>
                    </Button>
                </div>

                <RecordsFilters filters={filters} />

                {records.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-10 text-center">
                        <p className="font-medium">
                            {filtered
                                ? 'No records match these filters.'
                                : 'No records yet.'}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {filtered
                                ? 'Try a different search or clear the filters.'
                                : 'Upload a video to transcribe it and generate its metadata.'}
                        </p>
                    </div>
                ) : (
                    <>
                        <RecordsTable
                            records={records.data}
                            showCreator={showCreator}
                        />
                        <SimplePagination page={records} />
                    </>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Records', href: index() }],
};
