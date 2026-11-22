import { AudioLines, Download, RotateCcw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatBytes } from '@/lib/utilities';
import type { AudioExport } from '@/types/records';

type Props = {
    audio: AudioExport;
    pending: boolean;
    onPrepare: () => void;
};

/**
 * One audio format: prepare it (made from the master in the background),
 * then download it. A failure says why and can be tried again.
 */
export default function AudioExportRow({ audio, pending, onPrepare }: Props) {
    const working = pending || audio.status === 'processing';

    return (
        <li className="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-md bg-white/5 px-3 py-2 text-sm">
            <AudioLines
                className="size-4 shrink-0 text-neutral-400"
                aria-hidden
            />
            <div className="min-w-0 flex-1">
                <p>{audio.label}</p>
                <p className="text-xs wrap-anywhere text-neutral-500">
                    {audio.status === 'ready' && audio.file_size !== null
                        ? formatBytes(audio.file_size)
                        : audio.status === 'processing'
                          ? 'Preparing… a long video takes a few minutes.'
                          : audio.status === 'failed'
                            ? audio.error
                            : 'Made from the master when you ask for it.'}
                </p>
            </div>
            {audio.status === 'ready' && audio.download_url ? (
                <Button size="sm" variant="secondary" asChild>
                    <a href={audio.download_url} download>
                        <Download aria-hidden />
                        Download
                    </a>
                </Button>
            ) : (
                <Button
                    size="sm"
                    variant="secondary"
                    onClick={onPrepare}
                    disabled={working}
                >
                    {working ? (
                        <Spinner />
                    ) : audio.status === 'failed' ? (
                        <RotateCcw aria-hidden />
                    ) : null}
                    {working
                        ? 'Preparing'
                        : audio.status === 'failed'
                          ? 'Try again'
                          : 'Prepare'}
                </Button>
            )}
        </li>
    );
}
