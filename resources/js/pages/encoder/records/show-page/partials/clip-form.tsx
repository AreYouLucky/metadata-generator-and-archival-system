import { Eraser, Play, Scissors } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useCreateClip } from '@/hooks/use-clips';
import { formatTimestamp, getErrorMessage } from '@/lib/utilities';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { ClipLimits } from '@/types/clips';
import type { Chapter } from '@/types/records';
import ClipTimeField from './clip-time-field';

type Props = {
    recordId: string;
    chapters: Chapter[];
    /** Seconds; the end of the last chapter. */
    duration: number;
    limits: ClipLimits;
};

/**
 * Mark a range and cut it: in and out points (typed, from the playhead,
 * I / O, Shift-click on transcript lines, a quote's Clip button) or a
 * chapter, plus an optional title. The marks stay until cleared, so the
 * range can be previewed and adjusted first.
 */
export default function ClipForm({
    recordId,
    chapters,
    duration,
    limits,
}: Props) {
    const marks = useWorkspaceStore((state) => state.clipMarks);
    const setClipIn = useWorkspaceStore((state) => state.setClipIn);
    const setClipOut = useWorkspaceStore((state) => state.setClipOut);
    const markClip = useWorkspaceStore((state) => state.markClip);
    const setClipTitle = useWorkspaceStore((state) => state.setClipTitle);
    const clearClip = useWorkspaceStore((state) => state.clearClip);
    const seek = useWorkspaceStore((state) => state.seek);
    const create = useCreateClip(recordId);

    const length =
        marks.start !== null && marks.end !== null
            ? marks.end - marks.start
            : null;
    const problem =
        length === null
            ? 'Mark an in and an out point.'
            : length < limits.min_seconds
              ? `A clip must be at least ${limits.min_seconds} s long.`
              : length > limits.max_seconds
                ? `A clip can be at most ${formatTimestamp(limits.max_seconds)} long.`
                : duration > 0 && (marks.end ?? 0) > duration + 0.5
                  ? 'The clip ends after the video does.'
                  : null;

    const fromChapter = (index: string) => {
        const position = Number(index);
        const chapter = chapters[position];

        if (chapter) {
            markClip(
                chapter.start,
                chapters[position + 1]?.start ?? duration,
                chapter.title,
            );
        }
    };

    const submit = () => {
        if (problem !== null || marks.start === null || marks.end === null) {
            return;
        }

        create.mutate(
            {
                start: marks.start,
                end: marks.end,
                title: marks.title.trim() || null,
            },
            {
                onSuccess: () => {
                    toast.success('Cutting the clip…');
                    clearClip();
                },
                onError: (error) =>
                    toast.error(
                        getErrorMessage(error, 'The clip could not be made.'),
                    ),
            },
        );
    };

    return (
        <div className="grid gap-3 rounded-lg border border-border p-3">
            <div className="grid grid-cols-2 gap-2">
                <ClipTimeField
                    id="clip-in"
                    label="In"
                    shortcut="I"
                    value={marks.start}
                    onChange={setClipIn}
                />
                <ClipTimeField
                    id="clip-out"
                    label="Out"
                    shortcut="O"
                    value={marks.end}
                    onChange={setClipOut}
                />
            </div>

            {chapters.length > 0 && (
                <Select value="" onValueChange={fromChapter}>
                    <SelectTrigger
                        className="h-8 text-xs"
                        aria-label="Clip a chapter"
                    >
                        <SelectValue placeholder="Or clip a whole chapter…" />
                    </SelectTrigger>
                    <SelectContent>
                        {chapters.map((chapter, index) => (
                            <SelectItem
                                key={`${chapter.start}-${index}`}
                                value={String(index)}
                            >
                                {formatTimestamp(chapter.start)} {chapter.title}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            )}

            <Input
                value={marks.title}
                onChange={(event) => setClipTitle(event.target.value)}
                maxLength={200}
                placeholder="Title (optional)"
                aria-label="Clip title"
                className="h-8 text-sm"
            />

            <div className="flex items-center justify-between gap-2">
                <p className="text-xs text-muted-foreground tabular-nums">
                    {problem ??
                        `Length ${formatTimestamp(length ?? 0)} · cut from the master as MP4`}
                </p>
                <div className="flex shrink-0 gap-1">
                    {marks.start !== null && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            onClick={() => seek(marks.start ?? 0)}
                            aria-label="Play from the in point"
                            title="Play from the in point"
                        >
                            <Play className="size-3.5" />
                        </Button>
                    )}
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={clearClip}
                        aria-label="Clear the marks"
                        title="Clear the marks"
                    >
                        <Eraser className="size-3.5" />
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        className="h-8"
                        disabled={problem !== null || create.isPending}
                        onClick={submit}
                    >
                        {create.isPending ? <Spinner /> : <Scissors />}
                        Cut clip
                    </Button>
                </div>
            </div>
        </div>
    );
}
