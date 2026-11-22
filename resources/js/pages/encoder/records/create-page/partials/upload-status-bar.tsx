import { CheckCircle2, CircleDashed, FolderSearch } from 'lucide-react';
import { formatBytes } from '@/lib/utilities';

export type UploadProgress = {
    /** Bytes sent so far across every file. */
    sent: number;
    startedAt: number;
    /** Which clip is uploading (0-based). */
    itemIndex: number;
};

type Props = {
    itemCount: number;
    /** Total size of the files to upload (web addresses need none). */
    totalBytes: number;
    progress: UploadProgress | null;
    pending: boolean;
    projectChosen: boolean;
};

/**
 * Render-bar style status: bytes sent, speed and time left while uploading,
 * and which clip of a batch is on its way.
 */
export default function UploadStatusBar({
    itemCount,
    totalBytes,
    progress,
    pending,
    projectChosen,
}: Props) {
    const sent = progress?.sent ?? 0;
    const fraction = totalBytes > 0 ? Math.min(1, sent / totalBytes) : 1;
    const elapsed = progress
        ? (performance.now() - progress.startedAt) / 1000
        : 0;
    const speed = elapsed > 1 ? sent / elapsed : 0;
    const remaining = speed > 0 ? (totalBytes - sent) / speed : null;

    return (
        <footer
            className="flex min-h-11 shrink-0 flex-wrap items-center gap-x-4 gap-y-1 border-t border-border bg-card px-3 py-2 text-xs text-muted-foreground"
            aria-live="polite"
        >
            {pending && progress ? (
                <>
                    <span className="font-medium text-foreground">
                        {fraction < 1
                            ? itemCount > 1
                                ? `Uploading ${progress.itemIndex + 1} of ${itemCount}`
                                : 'Uploading'
                            : itemCount > 1
                              ? 'Creating the batch and starting processing'
                              : 'Creating the record and starting processing'}
                    </span>
                    <div
                        className="h-1.5 min-w-40 flex-1 overflow-hidden rounded-full bg-muted"
                        role="progressbar"
                        aria-valuemin={0}
                        aria-valuemax={100}
                        aria-valuenow={Math.round(fraction * 100)}
                        aria-label="Upload progress"
                    >
                        <div
                            className="h-full bg-brand transition-[width]"
                            style={{ width: `${fraction * 100}%` }}
                        />
                    </div>
                    <span className="tabular-nums">
                        {Math.floor(fraction * 100)}%
                        {totalBytes > 0 &&
                            ` · ${formatBytes(sent)} of ${formatBytes(totalBytes)}`}
                        {speed > 0 && ` · ${formatBytes(speed)}/s`}
                        {remaining !== null &&
                            fraction < 1 &&
                            ` · ${Math.ceil(remaining)} s left`}
                    </span>
                </>
            ) : itemCount > 0 && !projectChosen ? (
                <span className="flex items-center gap-1.5 text-amber-700">
                    <FolderSearch className="size-3.5" aria-hidden />
                    Choose the project (event) these videos belong to.
                </span>
            ) : itemCount > 0 ? (
                <span className="flex items-center gap-1.5">
                    <CheckCircle2
                        className="size-3.5 text-emerald-400"
                        aria-hidden
                    />
                    Ready to import {itemCount}{' '}
                    {itemCount === 1 ? 'clip' : 'clips'}. Uploads resume
                    automatically after a dropped connection.
                </span>
            ) : (
                <span className="flex items-center gap-1.5">
                    <CircleDashed className="size-3.5" aria-hidden />
                    No clip chosen
                </span>
            )}
        </footer>
    );
}
