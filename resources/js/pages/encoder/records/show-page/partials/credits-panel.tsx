import type { ReactNode } from 'react';
import { toast } from 'sonner';
import EditorPanel from '@/components/encoder/editor-panel';
import { useSaveContributors } from '@/hooks/use-save-contributors';
import { getErrorMessage } from '@/lib/utilities';
import type { Contributor, ContributorRoleOption } from '@/types/records';
import CreditAddForm from './credit-add-form';
import CreditRow from './credit-row';

type Props = {
    recordId: string;
    /** The inspector tabs, shown in the header's top corner. */
    heading: ReactNode;
    contributors: Contributor[];
    roles: ContributorRoleOption[];
    canEdit: boolean;
};

/**
 * Cast and production credits (producer, director, videographer, editor,
 * presenter / host, cast, contributors). Every change saves the whole list
 * in order, so rows can be edited, moved and removed independently.
 */
export default function CreditsPanel({
    recordId,
    heading,
    contributors,
    roles,
    canEdit,
}: Props) {
    const save = useSaveContributors(recordId);

    const commit = (next: Contributor[], success?: string) =>
        save.mutate(next, {
            onSuccess: () => success && toast.success(success),
            onError: (error) =>
                toast.error(
                    getErrorMessage(error, 'The credits could not be saved.'),
                ),
        });

    const move = (index: number, offset: number) => {
        const next = [...contributors];
        const [row] = next.splice(index, 1);
        next.splice(index + offset, 0, row);
        commit(next);
    };

    return (
        <EditorPanel title="Credits" heading={heading}>
            <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-5">
                {contributors.length === 0 ? (
                    <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        No credits yet.
                        {canEdit &&
                            ' Add the cast and crew below, or pause the video, click “Copy text” and drag over the names shown on screen.'}
                    </p>
                ) : (
                    <ol className="space-y-1.5">
                        {contributors.map((contributor, index) => (
                            <li key={contributor.id ?? `new-${index}`}>
                                <CreditRow
                                    contributor={contributor}
                                    roles={roles}
                                    canEdit={canEdit}
                                    disabled={save.isPending}
                                    isFirst={index === 0}
                                    isLast={index === contributors.length - 1}
                                    onChange={(changed) =>
                                        commit(
                                            contributors.map((row, position) =>
                                                position === index
                                                    ? changed
                                                    : row,
                                            ),
                                        )
                                    }
                                    onMove={(offset) => move(index, offset)}
                                    onRemove={() =>
                                        commit(
                                            contributors.filter(
                                                (_, position) =>
                                                    position !== index,
                                            ),
                                            `${contributor.name} removed.`,
                                        )
                                    }
                                />
                            </li>
                        ))}
                    </ol>
                )}

                {canEdit && (
                    <CreditAddForm
                        roles={roles}
                        disabled={save.isPending}
                        onAdd={(credit) =>
                            commit(
                                [...contributors, credit],
                                `${credit.name} added.`,
                            )
                        }
                    />
                )}
            </div>
        </EditorPanel>
    );
}
