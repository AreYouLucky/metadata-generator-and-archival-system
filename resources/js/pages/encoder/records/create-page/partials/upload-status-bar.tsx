import { CheckCircle2, CircleDashed, FolderSearch } from 'lucide-react';
import { formatBytes } from '@/lib/utilities';

export type UploadProgress = {
    fraction: number;
    startedAt: number;
};

type Props = {
    file: File | null;
    progress: UploadProgress | null;
    pending: boolean;
    projectChosen: boolean;
};

/**
 * Render-bar style status: bytes sent, speed and time left while uploading.
 */
export default function UploadStatusBar({
    file,
    progress,
    pending,
    projectChosen,
}: Props) {
    const fraction = progress?.fraction ?? 0;
    const sent = file ? file.size * fraction : 0;
    const elapsed = progress
        ? (performance.now() - progress.startedAt) / 1000
        : 0;
    const speed = elapsed > 1 ? sent / elapsed : 0;
    const remaining = speed > 0 && file ? (file.size - sent) / speed : null;

    return (
        <footer
            className="flex min-h-11 shrink-0 flex-wrap items-center gap-x-4 gap-y-1 border-t border-white/10 bg-neutral-900 px-3 py-2 text-xs text-neutral-400"
            aria-live="polite"
        >
            {pending && progress ? (
                <>
                    <span className="font-medium text-neutral-200">
                        {fraction < 1
                            ? 'Uploading'
                            : 'Creating the record and starting processing'}
                    </span>
                    <div
                        className="h-1.5 min-w-40 flex-1 overflow-hidden rounded-full bg-white/10"
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
                        {file &&
                            ` · ${formatBytes(sent)} of ${formatBytes(file.size)}`}
                        {speed > 0 && ` · ${formatBytes(speed)}/s`}
                        {remaining !== null &&
                            fraction < 1 &&
                            ` · ${Math.ceil(remaining)} s left`}
                    </span>
                </>
            ) : file && !projectChosen ? (
                <span className="flex items-center gap-1.5 text-amber-300">
                    <FolderSearch className="size-3.5" aria-hidden />
                    Choose the project (event) this video belongs to.
                </span>
            ) : file ? (
                <span className="flex items-center gap-1.5">
                    <CheckCircle2
                        className="size-3.5 text-emerald-400"
                        aria-hidden
                    />
                    Ready to import. Uploads resume automatically after a
                    dropped connection.
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
