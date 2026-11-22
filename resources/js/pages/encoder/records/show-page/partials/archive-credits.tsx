import { Users } from 'lucide-react';
import AgencySelect from '@/components/encoder/agency-select';
import { Button } from '@/components/ui/button';
import { useArchiveForm } from '@/hooks/use-archive-form';
import { useUpdateRecord } from '@/hooks/use-archive';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { Archive } from '@/types/archive';
import ArchiveSaveBar from './archive-save-bar';

type Props = {
    recordId: string;
    archive: Archive;
    canEdit: boolean;
};

/**
 * The producing agency and its production unit. The people (producer,
 * director, presenter…) are edited in the Credits tab.
 */
export default function ArchiveCredits({ recordId, archive, canEdit }: Props) {
    const { values, change, dirty, feedback } = useArchiveForm(archive.credits);
    const save = useUpdateRecord(recordId);
    const setTab = useWorkspaceStore((state) => state.setInspectorTab);

    return (
        <div className="grid gap-4">
            <AgencySelect
                idPrefix="archive-agency"
                agencyId={values.agency_id}
                divisionId={values.division_id}
                withUnit
                disabled={!canEdit}
                onChange={(agencyId, divisionId) =>
                    change({ agency_id: agencyId, division_id: divisionId })
                }
            />
            <div className="flex items-center gap-3 rounded-md bg-muted/60 p-3 text-sm">
                <Users className="size-4 shrink-0 text-brand" aria-hidden />
                <p className="flex-1 text-foreground/80">
                    Producer, director, videographer, editor, presenter and cast
                    are in the Credits tab.
                </p>
                <Button
                    size="sm"
                    variant="secondary"
                    className="h-7"
                    onClick={() => setTab('credits')}
                >
                    Open
                </Button>
            </div>
            {canEdit && (
                <ArchiveSaveBar
                    dirty={dirty}
                    pending={save.isPending}
                    onSave={() =>
                        save.mutate(values, feedback('Agency saved.'))
                    }
                />
            )}
        </div>
    );
}
