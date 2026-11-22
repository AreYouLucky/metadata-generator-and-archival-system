import { Head } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import Heading from '@/components/heading';
import { useBatchChannel } from '@/hooks/use-batch';
import { formatDateTime } from '@/lib/utilities';
import { show } from '@/routes/batches';
import { index as recordsIndex } from '@/routes/records';
import type { Batch, BatchRecord } from '@/types/batches';
import type { RecordProgressEvent } from '@/types/records';
import BatchRecordRow from './partials/batch-record-row';
import BatchZipPanel from './partials/batch-zip-panel';

type Props = {
    batch: Batch;
    records: BatchRecord[];
};

/**
 * A batch (PROJECT_SPEC §9, pages/encoder/batches/show.tsx): every record's
 * progress, live over Reverb, and the ZIP of their exports.
 */
export default function BatchShow({ batch, records: initialRecords }: Props) {
    const [records, setRecords] = useState(initialRecords);
    const [synced, setSynced] = useState(initialRecords);

    // A partial reload (counts or ZIP changed) brings fresh rows.
    if (synced !== initialRecords) {
        setSynced(initialRecords);
        setRecords(initialRecords);
    }

    const onRecordProgress = useCallback((event: RecordProgressEvent) => {
        setRecords((current) =>
            current.map((record) =>
                record.id !== event.record_id
                    ? record
                    : {
                          ...record,
                          processing_status: event.processing_status,
                          stages: record.stages.map((stage) =>
                              stage.stage === event.stage.stage
                                  ? { ...stage, ...event.stage }
                                  : stage,
                          ),
                      },
            ),
        );
    }, []);

    useBatchChannel(batch.id, { onRecordProgress });

    const included = records.filter(
        (record) =>
            record.zip_exclusions.length === 0 &&
            record.processing_status !== 'queued' &&
            record.processing_status !== 'processing',
    ).length;
    const finished = batch.completed + batch.failed;

    return (
        <>
            <Head title={batch.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading
                    title={batch.name}
                    description={
                        'Uploaded ' +
                        formatDateTime(batch.created_at) +
                        ' · ' +
                        (batch.status === 'done'
                            ? 'All ' + batch.total + ' processed'
                            : finished + ' of ' + batch.total + ' processed') +
                        (batch.failed > 0
                            ? ' · ' + batch.failed + ' failed'
                            : '')
                    }
                />

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
                    {records.length === 0 ? (
                        <div className="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                            Every record of this batch has been deleted.
                        </div>
                    ) : (
                        <ul
                            className="divide-y rounded-xl border"
                            aria-label="Records in this batch"
                        >
                            {records.map((record) => (
                                <BatchRecordRow
                                    key={record.id}
                                    record={record}
                                />
                            ))}
                        </ul>
                    )}
                    <BatchZipPanel batch={batch} includedCount={included} />
                </div>
            </div>
        </>
    );
}

BatchShow.layout = ({ batch }: Props) => ({
    breadcrumbs: [
        { title: 'Records', href: recordsIndex() },
        { title: batch.name, href: show(batch.id) },
    ],
});
