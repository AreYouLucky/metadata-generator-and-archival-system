import { AlertTriangle, Download, Play, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useDeleteClip } from '@/hooks/use-clips';
import { useTimeFormat } from '@/hooks/use-time-format';
import { formatBytes, formatTimestamp, getErrorMessage } from '@/lib/utilities';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { Clip } from '@/types/clips';

type Props = {
    recordId: string;
    clip: Clip;
};

/**
 * One clip: its range (click to play), state while it is cut, then its
 * size, format and download; delete once it is no longer being cut.
 */
export default function ClipRow({ recordId, clip }: Props) {
    const seek = useWorkspaceStore((state) => state.seek);
    const format = useTimeFormat(formatTimestamp);
    const remove = useDeleteClip(recordId);
    const busy = clip.status === 'queued' || clip.status === 'processing';
    const details = [
        clip.file_size !== null ? formatBytes(clip.file_size) : null,
        clip.technical?.resolution,
        clip.technical?.video_codec?.toUpperCase(),
    ].filter(Boolean);

    return (
        <li className="grid gap-1.5 rounded-md bg-muted/60 p-2">
            <div className="flex items-start gap-2">
                <button
                    type="button"
                    onClick={() => seek(clip.start)}
                    className="flex min-w-0 flex-1 items-start gap-2 rounded text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    aria-label={`Play the clip from ${format(clip.start)}`}
                >
                    <Play
                        className="mt-0.5 size-3.5 shrink-0 text-brand"
                        aria-hidden
                    />
                    <span className="min-w-0">
                        <span className="block truncate text-sm">
                            {clip.title || 'Untitled clip'}
                        </span>
                        <span className="font-mono text-xs text-muted-foreground tabular-nums">
                            {format(clip.start)}–{format(clip.end)} ·{' '}
                            {formatTimestamp(clip.end - clip.start)}
                        </span>
                    </span>
                </button>
                <div className="flex shrink-0 items-center gap-0.5">
                    {clip.download_url && (
                        <Button
                            asChild
                            variant="ghost"
                            size="icon"
                            className="size-7"
                        >
                            <a
                                href={clip.download_url}
                                download
                                aria-label="Download the clip"
                                title="Download (MP4)"
                            >
                                <Download className="size-3.5" />
                            </a>
                        </Button>
                    )}
                    {!busy && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-7 text-red-700 hover:text-red-800"
                            disabled={remove.isPending}
                            onClick={() =>
                                remove.mutate(clip.id, {
                                    onError: (error) =>
                                        toast.error(
                                            getErrorMessage(
                                                error,
                                                'The clip could not be deleted.',
                                            ),
                                        ),
                                })
                            }
                            aria-label="Delete the clip"
                            title="Delete"
                        >
                            <Trash2 className="size-3.5" />
                        </Button>
                    )}
                </div>
            </div>
            {busy ? (
                <p className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Spinner />
                    {clip.status === 'queued' ? 'Waiting…' : 'Cutting…'}
                </p>
            ) : clip.status === 'failed' ? (
                <p className="flex items-start gap-1.5 text-xs text-red-700">
                    <AlertTriangle
                        className="mt-0.5 size-3 shrink-0"
                        aria-hidden
                    />
                    {clip.error ?? 'The clip could not be cut.'}
                </p>
            ) : (
                details.length > 0 && (
                    <p
                        className="text-xs text-muted-foreground"
                        title={
                            clip.sha256 ? `SHA-256 ${clip.sha256}` : undefined
                        }
                    >
                        {details.join(' · ')}
                    </p>
                )
            )}
        </li>
    );
}
