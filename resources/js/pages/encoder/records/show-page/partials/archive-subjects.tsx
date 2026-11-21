import { Plus } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useSubjectMutations } from '@/hooks/use-archive';
import { formatEnumLabel, getErrorMessage } from '@/lib/utilities';
import type { ArchiveSubject, Option } from '@/types/archive';
import ArchiveSelect from './archive-select';
import SuggestionChip from './suggestion-chip';

type Props = {
    recordId: string;
    subjects: ArchiveSubject[];
    types: Option[];
    canEdit: boolean;
};

/**
 * Subjects: people, organisations, places, topics and events the video is
 * about. Only confirmed ones are ever public.
 */
export default function ArchiveSubjects({
    recordId,
    subjects,
    types,
    canEdit,
}: Props) {
    const { add, update, remove } = useSubjectMutations(recordId);
    const [subject, setSubject] = useState('');
    const [type, setType] = useState<string>('topic');
    const pending = subjects.filter((item) => !item.is_confirmed).length;
    const onError = (error: unknown) => toast.error(getErrorMessage(error));

    return (
        <section className="grid gap-2 border-t border-white/10 pt-4">
            <h3 className="text-sm font-medium">
                Subjects
                {pending > 0 && (
                    <span className="ml-2 text-xs font-normal text-brand">
                        {pending} to review
                    </span>
                )}
            </h3>
            {subjects.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No subjects yet.
                </p>
            ) : (
                <ul className="flex flex-wrap gap-1.5">
                    {subjects.map((item) => (
                        <SuggestionChip
                            key={item.id}
                            label={
                                <>
                                    {item.subject}
                                    <span className="ml-1 text-neutral-500">
                                        {formatEnumLabel(item.type)}
                                    </span>
                                </>
                            }
                            confirmed={item.is_confirmed}
                            source={item.source}
                            disabled={!canEdit}
                            onConfirm={() =>
                                update.mutate(
                                    { id: item.id, is_confirmed: true },
                                    { onError },
                                )
                            }
                            onRemove={() => remove.mutate(item.id, { onError })}
                        />
                    ))}
                </ul>
            )}
            {canEdit && (
                <form
                    className="flex gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        add.mutate(
                            { subject: subject.trim(), type },
                            { onSuccess: () => setSubject(''), onError },
                        );
                    }}
                >
                    <Input
                        value={subject}
                        maxLength={250}
                        placeholder="Add a subject"
                        aria-label="New subject"
                        onChange={(event) => setSubject(event.target.value)}
                        className="min-w-0 flex-1"
                    />
                    <div className="w-36">
                        <ArchiveSelect
                            id="subject-type"
                            value={type}
                            options={types}
                            onChange={(value) => setType(value ?? 'topic')}
                        />
                    </div>
                    <Button
                        type="submit"
                        size="icon"
                        variant="secondary"
                        disabled={subject.trim() === '' || add.isPending}
                        aria-label="Add subject"
                    >
                        <Plus aria-hidden />
                    </Button>
                </form>
            )}
        </section>
    );
}
