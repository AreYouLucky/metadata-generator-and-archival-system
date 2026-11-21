import { useState } from 'react';
import { toast } from 'sonner';
import { Input } from '@/components/ui/input';
import { useUpdateSpeaker } from '@/hooks/use-speakers';
import { getErrorMessage, speakerColor, speakerName } from '@/lib/utilities';
import { cn } from '@/lib/utils';
import type { Speaker } from '@/types/records';

type Props = {
    recordId: string;
    speaker: Speaker;
    position: number;
    canEdit: boolean;
};

/**
 * One speaker in the manager: its label chip and a name field that saves on
 * Enter or when it loses focus.
 */
export default function SpeakerNameField({
    recordId,
    speaker,
    position,
    canEdit,
}: Props) {
    const [name, setName] = useState(speaker.display_name ?? '');
    const update = useUpdateSpeaker(recordId);

    const save = () => {
        if (name.trim() === (speaker.display_name ?? '')) {
            return;
        }

        update.mutate(
            { speakerId: speaker.id, displayName: name },
            {
                onSuccess: (saved) => {
                    setName(saved.display_name ?? '');
                    toast.success(
                        `${saved.label} is now ${speakerName(saved)}.`,
                    );
                },
                onError: (error) =>
                    toast.error(
                        getErrorMessage(
                            error,
                            'The speaker could not be renamed.',
                        ),
                    ),
            },
        );
    };

    return (
        <div className="flex items-center gap-2">
            <span
                className={cn(
                    'inline-flex h-6 w-10 shrink-0 items-center justify-center rounded-full text-xs font-medium ring-1',
                    speakerColor(position),
                )}
            >
                {speaker.label}
            </span>
            {canEdit ? (
                <Input
                    value={name}
                    maxLength={80}
                    placeholder={`Name for ${speaker.label}, e.g. Host`}
                    aria-label={`Name for ${speaker.label}`}
                    disabled={update.isPending}
                    onChange={(event) => setName(event.target.value)}
                    onBlur={save}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            save();
                        }
                    }}
                />
            ) : (
                <span className="text-sm">{speakerName(speaker)}</span>
            )}
        </div>
    );
}
