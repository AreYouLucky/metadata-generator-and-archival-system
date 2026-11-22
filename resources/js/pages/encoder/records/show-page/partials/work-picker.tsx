import { Plus } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    reloadArchive,
    useCreateWork,
    useUpdateRecord,
} from '@/hooks/use-archive';
import { useArchiveForm } from '@/hooks/use-archive-form';
import { getErrorMessage } from '@/lib/utilities';
import type { Archive, ArchiveOptions } from '@/types/archive';
import ArchiveField from './archive-field';
import ArchiveSaveBar from './archive-save-bar';
import ArchiveSelect from './archive-select';

type Props = {
    recordId: string;
    archive: Archive;
    options: ArchiveOptions;
    canEdit: boolean;
};

/**
 * Group this record with the other reels or parts of one work: pick a work
 * (or create one), and say which part this is.
 */
export default function WorkPicker({
    recordId,
    archive,
    options,
    canEdit,
}: Props) {
    const { values, change, dirty, errors, feedback } = useArchiveForm({
        work_id: archive.work?.id ?? null,
        part_number: archive.part_number,
        part_label: archive.part_label,
    });
    const [newTitle, setNewTitle] = useState('');
    const save = useUpdateRecord(recordId);
    const create = useCreateWork();

    return (
        <div className="grid gap-3">
            <div className="grid gap-3 @md:grid-cols-[1fr_5rem_8rem]">
                <ArchiveField
                    label="Work"
                    htmlFor="structure-work"
                    error={errors.work_id}
                >
                    <ArchiveSelect
                        id="structure-work"
                        value={values.work_id}
                        options={options.works.map((work) => ({
                            value: work.id,
                            label: work.title,
                        }))}
                        emptyLabel="Not part of a work"
                        disabled={!canEdit}
                        onChange={(value) => change({ work_id: value })}
                    />
                </ArchiveField>
                <ArchiveField
                    label="Part"
                    htmlFor="structure-part"
                    error={errors.part_number}
                >
                    <Input
                        id="structure-part"
                        type="number"
                        min={1}
                        value={values.part_number ?? ''}
                        disabled={!canEdit || !values.work_id}
                        onChange={(event) =>
                            change({
                                part_number:
                                    event.target.value === ''
                                        ? null
                                        : Number(event.target.value),
                            })
                        }
                    />
                </ArchiveField>
                <ArchiveField
                    label="Label"
                    htmlFor="structure-part-label"
                    error={errors.part_label}
                >
                    <Input
                        id="structure-part-label"
                        value={values.part_label ?? ''}
                        maxLength={100}
                        placeholder="Reel 1"
                        disabled={!canEdit || !values.work_id}
                        onChange={(event) =>
                            change({ part_label: event.target.value || null })
                        }
                    />
                </ArchiveField>
            </div>
            {canEdit && (
                <form
                    className="flex gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        create.mutate(
                            { title: newTitle.trim(), total_parts: null },
                            {
                                onSuccess: (work) => {
                                    setNewTitle('');
                                    change({
                                        work_id: work.id,
                                        part_number: values.part_number ?? 1,
                                    });
                                    reloadArchive(true);
                                    toast.success(
                                        'Work created; save to file this record under it.',
                                    );
                                },
                                onError: (error) =>
                                    toast.error(getErrorMessage(error)),
                            },
                        );
                    }}
                >
                    <Input
                        value={newTitle}
                        maxLength={250}
                        placeholder="New work title"
                        aria-label="New work title"
                        onChange={(event) => setNewTitle(event.target.value)}
                        className="min-w-0 flex-1"
                    />
                    <Button
                        type="submit"
                        size="sm"
                        variant="secondary"
                        disabled={newTitle.trim() === '' || create.isPending}
                    >
                        <Plus aria-hidden />
                        New work
                    </Button>
                </form>
            )}
            {canEdit && (
                <ArchiveSaveBar
                    dirty={dirty}
                    pending={save.isPending}
                    onSave={() =>
                        save.mutate(
                            values.work_id
                                ? values
                                : {
                                      work_id: null,
                                      part_number: null,
                                      part_label: null,
                                  },
                            feedback('Work saved.'),
                        )
                    }
                />
            )}
        </div>
    );
}
