import { BookmarkPlus, Copy, X } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useClipboard } from '@/hooks/use-clipboard';
import { formatChapterLines, formatTimestamp } from '@/lib/utilities';
import { useMetadataDraftStore } from '@/stores/metadata-draft-store';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { Chapter } from '@/types/records';

type Props = {
    chapters: Chapter[];
    disabled: boolean;
    onChange: (chapters: Chapter[]) => void;
};

/**
 * Chapters: add one at the playhead, click the time to seek, edit or remove
 * titles, and copy them in YouTube's "00:00 Intro" format. Chapters can also
 * be added and dragged on the timeline.
 */
export default function ChaptersList({ chapters, disabled, onChange }: Props) {
    const seek = useWorkspaceStore((state) => state.seek);
    const addChapter = useMetadataDraftStore((state) => state.addChapter);
    const [, copy] = useClipboard();

    const copyForYouTube = async () => {
        if (await copy(formatChapterLines(chapters))) {
            toast.success('Chapters copied in YouTube format.');
        }
    };

    return (
        <div className="grid gap-2">
            <div className="flex items-center justify-between">
                <span className="text-sm font-medium">Chapters</span>
                <div className="flex items-center gap-1">
                    {!disabled && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                if (
                                    !addChapter(
                                        useWorkspaceStore.getState()
                                            .currentTime,
                                    )
                                ) {
                                    toast.error(
                                        'There is already a chapter at the playhead.',
                                    );
                                }
                            }}
                        >
                            <BookmarkPlus aria-hidden />
                            Add at playhead
                        </Button>
                    )}
                    {chapters.length > 0 && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={copyForYouTube}
                            aria-label="Copy for YouTube"
                            title="Copy for YouTube"
                        >
                            <Copy aria-hidden />
                        </Button>
                    )}
                </div>
            </div>
            {chapters.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No chapters (short videos usually have none).
                </p>
            ) : (
                <ol className="grid gap-1.5">
                    {chapters.map((chapter, index) => (
                        <li
                            key={`${chapter.start}-${index}`}
                            className="flex items-center gap-2"
                        >
                            <button
                                type="button"
                                onClick={() => seek(chapter.start)}
                                className="w-16 shrink-0 rounded px-1 text-left font-mono text-xs text-brand tabular-nums hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                aria-label={`Play chapter from ${formatTimestamp(chapter.start)}`}
                            >
                                {formatTimestamp(chapter.start)}
                            </button>
                            <Input
                                value={chapter.title}
                                disabled={disabled}
                                maxLength={150}
                                aria-label={`Chapter ${index + 1} title`}
                                className="h-8"
                                onChange={(event) =>
                                    onChange(
                                        chapters.map((item, position) =>
                                            position === index
                                                ? {
                                                      ...item,
                                                      title: event.target.value,
                                                  }
                                                : item,
                                        ),
                                    )
                                }
                            />
                            {!disabled && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-8 shrink-0"
                                    onClick={() =>
                                        onChange(
                                            chapters.filter(
                                                (_, position) =>
                                                    position !== index,
                                            ),
                                        )
                                    }
                                    aria-label={`Remove chapter ${index + 1}`}
                                >
                                    <X aria-hidden />
                                </Button>
                            )}
                        </li>
                    ))}
                </ol>
            )}
        </div>
    );
}
