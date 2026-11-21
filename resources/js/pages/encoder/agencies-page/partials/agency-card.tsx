import { Pencil, Plus } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { useCreateAgency, useUpdateAgency } from '@/hooks/use-agencies';
import type { AdminAgency } from '@/types/agencies';
import AgencyFormDialog from './agency-form-dialog';
import DeleteAgencyButton from './delete-agency-button';

type Props = {
    agency: AdminAgency;
    canManage: boolean;
};

const label = (item: { name: string; acronym: string | null }) =>
    item.acronym ? `${item.acronym} — ${item.name}` : item.name;

/**
 * One agency with its production units. Admins also see add, rename and
 * delete buttons.
 */
export default function AgencyCard({ agency, canManage }: Props) {
    const createAgency = useCreateAgency();
    const updateAgency = useUpdateAgency();

    const rename = (id: number, name: string, acronym: string | null) => (
        <AgencyFormDialog
            trigger={
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    aria-label={`Rename ${name}`}
                    title={`Rename ${name}`}
                >
                    <Pencil aria-hidden />
                </Button>
            }
            heading="Rename"
            explanation="The new name shows everywhere it is used."
            submitLabel="Save"
            pending={updateAgency.isPending}
            initial={{ name, acronym: acronym ?? '' }}
            onSubmit={(values) =>
                updateAgency
                    .mutateAsync({ id, ...values })
                    .then(() => toast.success(`Saved ${values.name}.`))
            }
        />
    );

    return (
        <section className="rounded-xl border">
            <header className="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
                <div className="min-w-0">
                    <h2 className="font-medium">{label(agency)}</h2>
                    <p className="text-xs text-muted-foreground">
                        {agency.projects_count} projects ·{' '}
                        {agency.records_count} videos · {agency.units.length}{' '}
                        units
                    </p>
                </div>
                {canManage && (
                    <div className="flex items-center gap-1">
                        <AgencyFormDialog
                            trigger={
                                <Button variant="outline" size="sm">
                                    <Plus aria-hidden />
                                    Add unit
                                </Button>
                            }
                            heading={`New unit of ${agency.acronym ?? agency.name}`}
                            explanation="A production unit (division or office) inside this agency."
                            submitLabel="Add unit"
                            pending={createAgency.isPending}
                            onSubmit={(values) =>
                                createAgency
                                    .mutateAsync({
                                        ...values,
                                        parent_id: agency.id,
                                    })
                                    .then(() =>
                                        toast.success(`Added ${values.name}.`),
                                    )
                            }
                        />
                        {rename(agency.id, agency.name, agency.acronym)}
                        <DeleteAgencyButton
                            id={agency.id}
                            name={agency.name}
                            inUse={
                                agency.records_count > 0 ||
                                agency.projects_count > 0 ||
                                agency.units.length > 0
                            }
                        />
                    </div>
                )}
            </header>
            {agency.units.length === 0 ? (
                <p className="px-4 py-3 text-sm text-muted-foreground">
                    No production units.
                </p>
            ) : (
                <ul className="divide-y">
                    {agency.units.map((unit) => (
                        <li
                            key={unit.id}
                            className="flex items-center justify-between gap-3 px-4 py-2 text-sm"
                        >
                            <span className="min-w-0">
                                {label(unit)}
                                <span className="ml-2 text-xs text-muted-foreground">
                                    {unit.records_count} videos
                                </span>
                            </span>
                            {canManage && (
                                <div className="flex items-center gap-1">
                                    {rename(unit.id, unit.name, unit.acronym)}
                                    <DeleteAgencyButton
                                        id={unit.id}
                                        name={unit.name}
                                        inUse={unit.records_count > 0}
                                    />
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
