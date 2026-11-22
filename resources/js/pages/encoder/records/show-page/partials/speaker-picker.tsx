import { Check, ListChecks, Plus, UserRound, X } from 'lucide-react';
import { toast } from 'sonner';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useCreateSpeaker } from '@/hooks/use-speakers';
import { useUpdateSegment } from '@/hooks/use-update-segment';
import { getErrorMessage, speakerColor, speakerName } from '@/lib/utilities';
import { cn } from '@/lib/utils';
import type { TranscriptSegment as Segment, Speaker } from '@/types/records';

/** Which lines an "apply to…" action changes. */
export type SpeakerScope = 'all' | 'unassigned' | 'from-here';

const SCOPES: { value: SpeakerScope; label: string }[] = [
    { value: 'all', label: 'all lines' },
    { value: 'unassigned', label: 'lines without a speaker' },
    { value: 'from-here', label: 'this line to the end' },
];

type Props = {
    recordId: string;
    segment: Segment;
    speakers: Speaker[];
    canEdit: boolean;
    onSaved: (
        segment: Pick<Segment, 'id' | 'text' | 'is_edited' | 'speaker_id'>,
    ) => void;
    /** Apply this line's speaker to many lines at once. */
    onApply: (scope: SpeakerScope, speakerId: number) => void;
};

/**
 * The speaker chip above a line, in that speaker's colour. Encoders click it
 * to pick a speaker, add a new one (S1, S2…) or clear it, then apply that
 * speaker to all lines, the lines still without one, or the rest of the
 * transcript in one go.
 */
export default function SpeakerPicker({
    recordId,
    segment,
    speakers,
    canEdit,
    onSaved,
    onApply,
}: Props) {
    const update = useUpdateSegment(recordId);
    const create = useCreateSpeaker(recordId);
    const position = speakers.findIndex(
        (speaker) => speaker.id === segment.speaker_id,
    );
    const current = position >= 0 ? speakers[position] : undefined;
    const busy = update.isPending || create.isPending;

    if (!canEdit && !current) {
        return null;
    }

    const assign = (speakerId: number | null) =>
        update.mutate(
            { segmentId: segment.id, speakerId },
            {
                onSuccess: onSaved,
                onError: (error) =>
                    toast.error(
                        getErrorMessage(
                            error,
                            'The speaker could not be saved.',
                        ),
                    ),
            },
        );

    const chip = cn(
        'inline-flex h-5 items-center gap-1 rounded-full px-2 text-[11px] font-medium ring-1',
        current
            ? speakerColor(position)
            : 'text-muted-foreground ring-border hover:text-foreground',
    );

    if (!canEdit) {
        return <span className={chip}>{current && speakerName(current)}</span>;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                disabled={busy}
                className={cn(
                    chip,
                    !current &&
                        'opacity-0 group-hover:opacity-100 focus-visible:opacity-100 data-[state=open]:opacity-100',
                    'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                )}
                aria-label={
                    current
                        ? `Speaker: ${speakerName(current)}. Change speaker`
                        : 'Set speaker'
                }
            >
                <UserRound className="size-3" aria-hidden />
                {current ? speakerName(current) : 'Speaker'}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-52">
                {speakers.map((speaker, index) => (
                    <DropdownMenuItem
                        key={speaker.id}
                        onSelect={() => assign(speaker.id)}
                    >
                        <span
                            className={cn(
                                'size-2.5 rounded-full ring-1',
                                speakerColor(index),
                            )}
                            aria-hidden
                        />
                        <span className="flex-1 truncate">
                            {speakerName(speaker)}
                        </span>
                        {speaker.id === segment.speaker_id && (
                            <Check aria-hidden />
                        )}
                    </DropdownMenuItem>
                ))}
                {speakers.length > 0 && <DropdownMenuSeparator />}
                <DropdownMenuItem
                    onSelect={() =>
                        create.mutate(null, {
                            onSuccess: (speaker) => assign(speaker.id),
                            onError: (error) =>
                                toast.error(
                                    getErrorMessage(
                                        error,
                                        'The speaker could not be added.',
                                    ),
                                ),
                        })
                    }
                >
                    <Plus aria-hidden />
                    New speaker
                </DropdownMenuItem>
                {current && (
                    <DropdownMenuItem onSelect={() => assign(null)}>
                        <X aria-hidden />
                        No speaker
                    </DropdownMenuItem>
                )}
                {current && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
                            Apply “{speakerName(current)}” to…
                        </DropdownMenuLabel>
                        {SCOPES.map((scope) => (
                            <DropdownMenuItem
                                key={scope.value}
                                onSelect={() =>
                                    onApply(scope.value, current.id)
                                }
                            >
                                <ListChecks aria-hidden />
                                {scope.label}
                            </DropdownMenuItem>
                        ))}
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
