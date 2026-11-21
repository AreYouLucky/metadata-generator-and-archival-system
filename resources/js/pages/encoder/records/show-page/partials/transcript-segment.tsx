import { Pencil } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useUpdateSegment } from '@/hooks/use-update-segment';
import { formatTimestamp, getErrorMessage } from '@/lib/utilities';
import { cn } from '@/lib/utils';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { TranscriptSegment as Segment } from '@/types/records';
import HighlightedText from './highlighted-text';

type Props = {
    recordId: string;
    segment: Segment;
    active: boolean;
    query: string;
    canEdit: boolean;
    onSaved: (segment: Pick<Segment, 'id' | 'text' | 'is_edited'>) => void;
};

/**
 * One line: click the time to seek; edit inline (Enter saves, Shift+Enter
 * adds a line break, Esc cancels).
 */
export default function TranscriptSegment({
    recordId,
    segment,
    active,
    query,
    canEdit,
    onSaved,
}: Props) {
    const seek = useWorkspaceStore((state) => state.seek);
    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState(segment.text);
    const update = useUpdateSegment(recordId);

    const save = () => {
        const text = draft.trim();

        if (text === '' || text === segment.text) {
            setEditing(false);

            return;
        }

        update.mutate(
            { segmentId: segment.id, text },
            {
                onSuccess: (saved) => {
                    onSaved(saved);
                    setEditing(false);
                    toast.success('Line saved.');
                },
                onError: (error) =>
                    toast.error(
                        getErrorMessage(error, 'The line could not be saved.'),
                    ),
            },
        );
    };

    return (
        <div
            className={cn(
                'group flex gap-3 border-l-2 px-3 py-2 transition-colors',
                active
                    ? 'border-brand bg-brand-muted/60'
                    : 'border-transparent hover:bg-muted/40',
            )}
            aria-current={active ? 'true' : undefined}
        >
            <button
                type="button"
                onClick={() => seek(segment.start)}
                className="h-fit shrink-0 rounded px-1 font-mono text-xs text-brand tabular-nums hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                aria-label={`Play from ${formatTimestamp(segment.start)}`}
            >
                {formatTimestamp(segment.start)}
            </button>

            {editing ? (
                <div className="flex-1 space-y-2">
                    <Textarea
                        value={draft}
                        autoFocus
                        maxLength={5000}
                        disabled={update.isPending}
                        aria-label="Edit line"
                        onChange={(event) => setDraft(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter' && !event.shiftKey) {
                                event.preventDefault();
                                save();
                            }

                            if (event.key === 'Escape') {
                                setDraft(segment.text);
                                setEditing(false);
                            }
                        }}
                    />
                    <p className="text-xs text-muted-foreground">
                        Enter to save · Esc to cancel
                    </p>
                </div>
            ) : (
                <p className="flex-1 text-sm leading-relaxed break-words">
                    <HighlightedText text={segment.text} query={query} />
                    {segment.is_edited && (
                        <span
                            className="ml-2 text-xs text-muted-foreground"
                            title="Edited by hand"
                        >
                            (edited)
                        </span>
                    )}
                </p>
            )}

            {canEdit && !editing && (
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-7 shrink-0 opacity-0 group-hover:opacity-100 focus-visible:opacity-100"
                    onClick={() => {
                        setDraft(segment.text);
                        setEditing(true);
                    }}
                    aria-label="Edit line"
                >
                    <Pencil aria-hidden />
                </Button>
            )}
        </div>
    );
}
