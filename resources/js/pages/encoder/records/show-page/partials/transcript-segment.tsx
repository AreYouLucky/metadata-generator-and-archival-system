import { Pencil, Scissors, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { MouseEvent } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useTimeFormat } from '@/hooks/use-time-format';
import { useUpdateSegment } from '@/hooks/use-update-segment';
import { formatTimestamp, getErrorMessage } from '@/lib/utilities';
import { cn } from '@/lib/utils';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { TranscriptSegment as Segment, Speaker } from '@/types/records';
import HighlightedText from './highlighted-text';
import SpeakerPicker from './speaker-picker';
import type { SpeakerScope } from './speaker-picker';

type Props = {
    recordId: string;
    segment: Segment;
    speakers: Speaker[];
    active: boolean;
    query: string;
    canEdit: boolean;
    onSaved: (
        segment: Pick<Segment, 'id' | 'text' | 'is_edited' | 'speaker_id'>,
    ) => void;
    onDelete: (segment: Segment) => void;
    onApplySpeaker: (
        scope: SpeakerScope,
        speakerId: number,
        from: Segment,
    ) => void;
};

// Clicks on these belong to the control, not to "jump to this line".
const CONTROLS =
    'button, a, input, textarea, [role="menuitem"], [role="menu"], [role="dialog"]';

/**
 * One line: click it to jump the video there (selecting text to copy does
 * not jump). Edit inline (Enter saves, Shift+Enter adds a line break, Esc
 * cancels), set the speaker from the chip, or delete the line.
 */
export default function TranscriptSegment({
    recordId,
    segment,
    speakers,
    active,
    query,
    canEdit,
    onSaved,
    onDelete,
    onApplySpeaker,
}: Props) {
    const seek = useWorkspaceStore((state) => state.seek);
    const bumpCaptions = useWorkspaceStore((state) => state.bumpCaptions);
    const requested = useWorkspaceStore(
        (state) => state.editRequest === segment.id,
    );
    const requestEdit = useWorkspaceStore((state) => state.requestEdit);
    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState(segment.text);
    const update = useUpdateSegment(recordId);
    const formatTime = useTimeFormat(formatTimestamp);

    // The E shortcut asks the current line to open for editing.
    useEffect(() => {
        if (requested && canEdit) {
            setDraft(segment.text);
            setEditing(true);
            requestEdit(null);
        }
    }, [requested, canEdit, segment.text, requestEdit]);

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
                    bumpCaptions();
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

    const extendClip = useWorkspaceStore((state) => state.extendClip);
    const setInspectorTab = useWorkspaceStore((state) => state.setInspectorTab);
    // Inside the range marked for a clip (re-renders only when that changes).
    const inClip = useWorkspaceStore(
        ({ clipMarks }) =>
            clipMarks.start !== null &&
            clipMarks.end !== null &&
            segment.start < clipMarks.end &&
            segment.end > clipMarks.start,
    );

    /** Add this line to the clip range (Shift-click, or the scissors). */
    const addToClip = () => {
        extendClip(segment.start, segment.end);
        setInspectorTab('clips');
    };

    const jumpHere = (event: MouseEvent<HTMLDivElement>) => {
        const target = event.target as HTMLElement;

        if (event.shiftKey && !editing && !target.closest(CONTROLS)) {
            window.getSelection()?.removeAllRanges();
            addToClip();

            return;
        }

        if (
            editing ||
            target.closest(CONTROLS) ||
            (window.getSelection()?.toString() ?? '') !== ''
        ) {
            return;
        }

        seek(segment.start);
    };

    return (
        <div
            onClick={jumpHere}
            onMouseDown={(event) => {
                // Shift-click selects lines for a clip, not text.
                if (event.shiftKey && !editing) {
                    event.preventDefault();
                }
            }}
            className={cn(
                'group flex gap-3 border-l-2 px-3 py-2 transition-colors',
                !editing && 'cursor-pointer',
                active
                    ? 'border-brand bg-brand-muted/60'
                    : inClip
                      ? 'border-amber-400/70 bg-amber-400/10'
                      : 'border-transparent hover:bg-muted/40',
            )}
            aria-current={active ? 'true' : undefined}
        >
            <button
                type="button"
                onClick={() => seek(segment.start)}
                className="h-fit shrink-0 rounded px-1 font-mono text-xs text-brand tabular-nums hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                aria-label={`Play from ${formatTime(segment.start)}`}
            >
                {formatTime(segment.start)}
            </button>

            <div className="min-w-0 flex-1 space-y-1">
                <SpeakerPicker
                    recordId={recordId}
                    segment={segment}
                    speakers={speakers}
                    canEdit={canEdit}
                    onSaved={onSaved}
                    onApply={(scope, speakerId) =>
                        onApplySpeaker(scope, speakerId, segment)
                    }
                />

                {editing ? (
                    <div className="space-y-2">
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
                    <p
                        className="text-sm leading-relaxed wrap-break-word"
                        onDoubleClick={() => {
                            if (canEdit) {
                                setDraft(segment.text);
                                setEditing(true);
                            }
                        }}
                    >
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
            </div>

            {!editing && (
                <div className="flex shrink-0 flex-col gap-0.5 opacity-0 group-hover:opacity-100 focus-within:opacity-100">
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        onClick={addToClip}
                        aria-label="Add this line to the clip"
                        title="Add to the clip (or Shift-click the line)"
                    >
                        <Scissors aria-hidden />
                    </Button>
                </div>
            )}
            {canEdit && !editing && (
                <div className="flex shrink-0 flex-col gap-0.5 opacity-0 group-hover:opacity-100 focus-within:opacity-100">
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        onClick={() => {
                            setDraft(segment.text);
                            setEditing(true);
                        }}
                        aria-label="Edit line"
                        title="Edit (or double-click the text)"
                    >
                        <Pencil aria-hidden />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-7 text-red-700 hover:text-red-800"
                        onClick={() => onDelete(segment)}
                        aria-label="Delete line"
                        title="Delete line"
                    >
                        <Trash2 aria-hidden />
                    </Button>
                </div>
            )}
        </div>
    );
}
