import { Download, FileArchive, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useBuildBatchZip } from '@/hooks/use-batch';
import { formatDateTime, getErrorMessage } from '@/lib/utilities';
import { download } from '@/routes/batches';
import type { Batch } from '@/types/batches';

type Props = {
    batch: Batch;
    /** Records that will go into the ZIP (finished and not restricted). */
    includedCount: number;
};

/**
 * The batch ZIP: every finished record's exports (JSON, Markdown, SRT, VTT,
 * TXT, CSV), optionally thumbnails, and a manifest. Built in the background;
 * records the archive restricts and release forms are never included.
 */
export default function BatchZipPanel({ batch, includedCount }: Props) {
    const [thumbnails, setThumbnails] = useState(false);
    const build = useBuildBatchZip(batch.id);
    const building = batch.zip_status === 'building' || build.isPending;

    const start = () =>
        build.mutate(
            { thumbnails },
            {
                onSuccess: () =>
                    toast.info('Building the ZIP. It is ready in a moment.'),
                onError: (error) =>
                    toast.error(
                        getErrorMessage(error, 'The ZIP could not be built.'),
                    ),
            },
        );

    return (
        <section
            aria-labelledby="batch-zip"
            className="grid gap-3 rounded-xl border p-4"
        >
            <div className="flex items-start gap-3">
                <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-brand-muted text-brand">
                    <FileArchive className="size-4" aria-hidden />
                </span>
                <div className="grid gap-0.5">
                    <h2 id="batch-zip" className="font-medium">
                        Download as ZIP
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Metadata and transcripts of {includedCount} of{' '}
                        {batch.total} records. Classified, embargoed or
                        restricted records and release forms are left out; the
                        manifest says why.
                    </p>
                </div>
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id="zip-thumbnails"
                    checked={thumbnails}
                    disabled={building}
                    onCheckedChange={(value) => setThumbnails(value === true)}
                />
                <Label htmlFor="zip-thumbnails" className="font-normal">
                    Include thumbnails
                </Label>
            </div>

            {batch.zip_status === 'failed' && (
                <p className="text-sm text-red-600 dark:text-red-400">
                    The last ZIP could not be built. Try again.
                </p>
            )}

            <div className="flex flex-wrap items-center gap-2">
                {batch.zip_status === 'ready' && !building && (
                    <Button asChild>
                        <a href={download.url(batch.id)}>
                            <Download aria-hidden />
                            Download ZIP
                        </a>
                    </Button>
                )}
                <Button
                    variant={
                        batch.zip_status === 'ready' ? 'outline' : 'default'
                    }
                    onClick={start}
                    disabled={building || includedCount === 0}
                >
                    {building ? <Spinner /> : <RefreshCw aria-hidden />}
                    {building
                        ? 'Building…'
                        : batch.zip_status === 'ready'
                          ? 'Build again'
                          : 'Build ZIP'}
                </Button>
                {batch.zip_status === 'ready' && batch.zip_built_at && (
                    <span className="text-xs text-muted-foreground">
                        Built {formatDateTime(batch.zip_built_at)}
                    </span>
                )}
            </div>
        </section>
    );
}
