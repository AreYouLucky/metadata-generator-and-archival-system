import { MonitorPlay } from 'lucide-react';
import type { LocalVideo } from '@/hooks/use-local-video';

type Props = {
    file: File | null;
    video: LocalVideo;
};

/**
 * Source monitor: plays the chosen file straight from this computer, so the
 * encoder can check it is the right clip before uploading.
 */
export default function ImportPreview({ file, video }: Props) {
    if (!file || !video.url) {
        return (
            <div className="flex h-full min-h-72 flex-col items-center justify-center gap-2 text-neutral-500">
                <MonitorPlay className="size-10" aria-hidden />
                <p className="text-sm">The chosen clip plays here.</p>
            </div>
        );
    }

    if (video.playable === false) {
        return (
            <div className="flex h-full min-h-72 flex-col items-center justify-center gap-2 p-6 text-center text-neutral-400">
                <MonitorPlay className="size-10" aria-hidden />
                <p className="text-sm">
                    This browser can't preview this format (for example ProRes
                    or MXF).
                </p>
                <p className="text-xs text-neutral-500">
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
