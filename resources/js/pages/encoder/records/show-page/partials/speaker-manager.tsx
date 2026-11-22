import { Plus, Users } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useCreateSpeaker } from '@/hooks/use-speakers';
import { getErrorMessage } from '@/lib/utilities';
import type { Speaker } from '@/types/records';
import SpeakerNameField from './speaker-name-field';

type Props = {
    recordId: string;
    speakers: Speaker[];
    canEdit: boolean;
};

/**
 * Lists the record's speakers with their colours; encoders rename them
 * ("S1" → "Host") and add new ones.
 */
export default function SpeakerManager({ recordId, speakers, canEdit }: Props) {
    const create = useCreateSpeaker(recordId);

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-7 gap-1.5 px-2 text-xs text-neutral-300"
                >
                    <Users aria-hidden />
                    Speakers
                    {speakers.length > 0 && (
                        <span className="text-neutral-500">
                            {speakers.length}
                        </span>
                    )}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Speakers</DialogTitle>
                    <DialogDescription>
                        {canEdit
                            ? 'Name each speaker; the name shows on every line they speak. Assign lines from the chip above each line.'
                            : 'The people speaking in this record.'}
                    </DialogDescription>
                </DialogHeader>

                {speakers.length === 0 ? (
                    <p className="rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground">
                        No speakers yet.
                    </p>
                ) : (
                    <ul className="max-h-80 space-y-2 overflow-y-auto">
                        {speakers.map((speaker, index) => (
                            <li key={speaker.id}>
                                <SpeakerNameField
                                    recordId={recordId}
                                    speaker={speaker}
                                    position={index}
                                    canEdit={canEdit}
                                />
                            </li>
                        ))}
                    </ul>
                )}

                {canEdit && (
                    <Button
                        variant="secondary"
                        disabled={create.isPending}
                        onClick={() =>
                            create.mutate(null, {
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
                        {create.isPending ? <Spinner /> : <Plus aria-hidden />}
                        Add speaker
                    </Button>
                )}
            </DialogContent>
        </Dialog>
    );
}
