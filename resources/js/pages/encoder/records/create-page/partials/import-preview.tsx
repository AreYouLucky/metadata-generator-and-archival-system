import { Globe, MonitorPlay } from 'lucide-react';
import type { BinItem } from '@/hooks/use-import-media';
import { useLocalVideo } from '@/hooks/use-local-video';

type Props = {
    item: BinItem | null;
};

/**
 * Source monitor: plays the chosen file straight from this computer, so the
 * encoder can check it is the right clip before uploading. A web address is
 * downloaded by the server, so it only shows where it comes from.
 */
export default function ImportPreview({ item }: Props) {
    const video = useLocalVideo(item?.kind === 'file' ? item.file : null);

    if (item?.kind === 'url') {
        return (
            <div className="flex h-full min-h-72 flex-col items-center justify-center gap-2 p-6 text-center text-muted-foreground">
                <Globe className="size-10" aria-hidden />
                <p className="max-w-md text-sm break-all text-foreground">
                    {item.url}
                </p>
                <p className="max-w-md text-xs text-muted-foreground">
                    The server downloads this video (up to 1080p), then
                    checksums and processes it like an upload. Where it came
                    from is kept in the record's provenance.
                </p>
            </div>
        );
    }

    if (!item || !video.url) {
        return (
            <div className="flex h-full min-h-72 flex-col items-center justify-center gap-2 text-muted-foreground">
                <MonitorPlay className="size-10" aria-hidden />
                <p className="text-sm">The chosen clip plays here.</p>
            </div>
        );
    }

    if (video.playable === false) {
        return (
            <div className="flex h-full min-h-72 flex-col items-center justify-center gap-2 p-6 text-center text-muted-foreground">
                <MonitorPlay className="size-10" aria-hidden />
                <p className="text-sm">
                    This browser can't preview this format (for example ProRes
                    or MXF).
                </p>
                <p className="text-xs text-muted-foreground">
                    It will still upload, be checksummed and be transcribed.
                </p>
            </div>
        );
    }

    return (
        <div className="flex h-full min-h-72 items-center justify-center bg-black">
            <video
                key={video.url}
                src={video.url}
                controls
                playsInline
                className="max-h-full w-full object-contain xl:h-full"
            >
                This browser can't play this video.
            </video>
        </div>
    );
}
