import { Copy } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { formatTimestamp } from '@/lib/utilities';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { TranscriptSegment } from '@/types/records';

/**
 * While the video is paused, shows the line at the playhead with Copy and
 * "Copy with timestamp" (PROJECT_SPEC §9).
 */
export default function CurrentLineCopy({
    segments,
}: {
    segments: TranscriptSegment[];
}) {
    const paused = useWorkspaceStore((state) => state.paused);
    const currentTime = useWorkspaceStore((state) => state.currentTime);
    const activeIndex = useWorkspaceStore((state) => state.activeIndex);
    const [, copy] = useClipboard();
    const segment = activeIndex >= 0 ? segments[activeIndex] : undefined;

    // Not before the first play, and only while the playhead is inside the line.
    if (
        !paused ||
        !segment ||
        currentTime === 0 ||
        currentTime > segment.end + 1
    ) {
        return null;
    }

    const copyText = async (text: string) => {
        if (await copy(text)) {
            toast.success('Copied to the clipboard.');
        } else {
            toast.error('Copying is not available in this browser.');
        }
    };

    return (
        <div
            role="region"
            aria-label="Current line"
            className="fixed right-4 bottom-4 z-40 w-[min(28rem,calc(100vw-2rem))] rounded-xl border bg-popover p-4 text-popover-foreground shadow-xl"
        >
            <p className="mb-1 font-mono text-xs text-brand">
                {formatTimestamp(segment.start)} –{' '}
                {formatTimestamp(segment.end)}
            </p>
            <p className="mb-3 line-clamp-4 text-sm">{segment.text}</p>
            <div className="flex gap-2">
                <Button size="sm" onClick={() => copyText(segment.text)}>
                    <Copy aria-hidden />
                    Copy
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        copyText(
                            `[${formatTimestamp(segment.start)}] ${segment.text}`,
                        )
                    }
                >
                    Copy with timestamp
                </Button>
            </div>
        </div>
    );
}
