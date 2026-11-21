import { Pencil } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useUpdateTranslationLine } from '@/hooks/use-translations';
import { formatTimestamp, getErrorMessage } from '@/lib/utilities';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { TranslationLines } from '@/types/records';

type Props = {
    recordId: string;
    language: string;
    line: TranslationLines['segments'][number];
    canEdit: boolean;
};

/**
 * One translated line: click the time to play from there; correct the text
 * in place (pencil or double-click; Enter saves, Shift+Enter adds a line
 * break, Esc cancels). The machine translation is kept as the line's
 * original, as for the transcript.
 */
export default function TranslationLine({
    recordId,
    language,
    line,
    canEdit,
}: Props) {
    const seek = useWorkspaceStore((state) => state.seek);
    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState(line.text);
    const update = useUpdateTranslationLine(recordId, language);

    const startEditing = () => {
        if (canEdit) {
            setDraft(line.text);
            setEditing(true);
        }
    };

    const save = () => {
        const text = draft.trim();

        if (text === '' || text === line.text) {
            setEditing(false);

            return;
        }

        update.mutate(
            { segmentId: line.id, text },
            {
                onSuccess: () => {
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
        <li className="group flex gap-3 text-sm [content-visibility:auto]">
            <button
                type="button"
                onClick={() => seek(line.start)}
                className="h-fit shrink-0 rounded px-1 font-mono text-xs text-brand tabular-nums hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                aria-label={`Play from ${formatTimestamp(line.start)}`}
            >
                {formatTimestamp(line.start)}
            </button>

            {editing ? (
                <div className="min-w-0 flex-1 space-y-1">
                    <Textarea
                        value={draft}
                        autoFocus
                        rows={2}
                        maxLength={5000}
                        disabled={update.isPending}
                        aria-label="Edit translated line"
                        onChange={(event) => setDraft(event.target.value)}
                        onKeyDown={(event) => {
                            // Esc cancels the edit, not the whole dialog.
                            if (event.key === 'Escape') {
                                event.preventDefault();
                                event.stopPropagation();
                                setEditing(false);
                            }

                            if (event.key === 'Enter' && !event.shiftKey) {
                                event.preventDefault();
                                save();
                            }
                        }}
                    />
                    <p className="text-xs text-muted-foreground">
                        Enter to save · Esc to cancel
                    </p>
                </div>
            ) : (
                <p
                    className="min-w-0 flex-1 leading-relaxed break-words"
                    onDoubleClick={startEditing}
                >
                    {line.text}
                    {line.is_edited && (
                        <span
                            className="ml-2 text-xs text-muted-foreground"
                            title="Corrected by hand"
                        >
                            (edited)
                        </span>
                    )}
                </p>
            )}

            {canEdit && !editing && (
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-7 shrink-0 opacity-0 group-hover:opacity-100 focus-visible:opacity-100"
                    onClick={startEditing}
                    aria-label="Edit line"
                    title="Edit (or double-click the text)"
                >
                    <Pencil aria-hidden />
                </Button>
            )}
        </li>
    );
}
