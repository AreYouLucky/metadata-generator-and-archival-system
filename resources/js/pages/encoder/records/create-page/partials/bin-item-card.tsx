import { Film, Globe, X } from 'lucide-react';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import type { BinItem } from '@/hooks/use-import-media';
import { useLocalVideo } from '@/hooks/use-local-video';
import { formatBytes, formatTimestamp } from '@/lib/utilities';
import { cn } from '@/lib/utils';

type Props = {
    item: BinItem;
    selected: boolean;
    /** Over the size or duration limit: shown in red, and blocks the import. */
    invalid: boolean;
    disabled: boolean;
    onSelect: () => void;
    onRemove: () => void;
    /** Reports the clip's length once the browser has read it (files only). */
    onMeasured: (duration: number | null) => void;
};

/**
 * One clip in the media bin: a thumbnail card for a file (read in the
 * browser, nothing is uploaded yet), or the web address to import.
 */
export default function BinItemCard({
    item,
    selected,
    invalid,
    disabled,
    onSelect,
    onRemove,
    onMeasured,
}: Props) {
    const video = useLocalVideo(item.kind === 'file' ? item.file : null);

    useEffect(() => {
        if (item.kind === 'file') {
            onMeasured(video.duration);
        }
    }, [item.kind, video.duration, onMeasured]);

    const name =
        item.title ||
        (item.kind === 'file' ? item.file.name : hostOf(item.url));

    return (
        <li
            className={cn(
                'group relative flex gap-2 overflow-hidden rounded-md bg-muted p-1.5 ring-1',
                invalid
                    ? 'ring-red-500/60'
                    : selected
                      ? 'ring-brand'
                      : 'ring-border hover:ring-white/25',
            )}
        >
            <button
                type="button"
                onClick={onSelect}
                aria-pressed={selected}
                className="flex min-w-0 flex-1 items-center gap-2 text-left focus-visible:outline-none"
            >
                <span className="relative flex aspect-video w-20 shrink-0 items-center justify-center overflow-hidden rounded bg-black">
                    {item.kind === 'url' ? (
                        <Globe
                            className="size-5 text-muted-foreground"
                            aria-hidden
                        />
                    ) : video.thumbnail ? (
                        <img
                            src={video.thumbnail}
                            alt=""
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <Film
                            className="size-5 text-muted-foreground"
                            aria-hidden
                        />
                    )}
                    {video.duration !== null && (
                        <span className="absolute right-0.5 bottom-0.5 rounded bg-black/70 px-0.5 font-mono text-[9px] text-white">
                            {formatTimestamp(video.duration)}
                        </span>
                    )}
                </span>
                <span className="min-w-0 flex-1">
                    <span className="block truncate text-xs font-medium text-foreground">
                        {name}
                    </span>
                    <span className="block truncate text-[11px] text-muted-foreground">
                        {item.kind === 'file'
                            ? formatBytes(item.file.size)
                            : 'Downloaded by the server'}
                    </span>
                </span>
            </button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-6 shrink-0 self-start text-muted-foreground hover:text-white"
                disabled={disabled}
                onClick={onRemove}
                aria-label={`Remove ${name}`}
            >
                <X aria-hidden />
            </Button>
        </li>
    );
}

function hostOf(url: string): string {
    try {
        return new URL(url).host;
    } catch {
        return url;
    }
}
