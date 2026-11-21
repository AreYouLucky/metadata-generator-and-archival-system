import { Head } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useCreateAgency } from '@/hooks/use-agencies';
import { index } from '@/routes/agencies';
import type { AdminAgency } from '@/types/agencies';
import AgencyCard from './partials/agency-card';
import AgencyFormDialog from './partials/agency-form-dialog';

type Props = {
    agencies: AdminAgency[];
    canManage: boolean;
};

/**
 * Producing agencies and their production units. Encoders browse the list;
 * admins add, rename and delete (only while unused).
 */
export default function Agencies({ agencies, canManage }: Props) {
    const createAgency = useCreateAgency();

    return (
        <>
            <Head title="Agencies" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Agencies"
                        description="The agencies that produce the videos, and their production units. Projects and videos are credited to them."
                    />
                    {canManage && (
                        <AgencyFormDialog
                            trigger={
                                <Button>
                                    <Building2 aria-hidden />
                                    New agency
                                </Button>
                            }
                            heading="New agency"
                            explanation="A producing agency, e.g. a department or bureau."
                            submitLabel="Add agency"
                            pending={createAgency.isPending}
                            onSubmit={(values) =>
                                createAgency
                                    .mutateAsync(values)
                                    .then(() =>
                                        toast.success(`Added ${values.name}.`),
                                    )
                            }
                        />
                    )}
                </div>

                {agencies.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-10 text-center">
                        <p className="font-medium">No agencies yet.</p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {canManage
                                ? 'Add the producing agencies so projects and videos can be credited to them.'
                                : 'An admin adds the producing agencies.'}
                        </p>
                    </div>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {agencies.map((agency) => (
                            <AgencyCard
                                key={agency.id}
                                agency={agency}
                                canManage={canManage}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

Agencies.layout = {
    breadcrumbs: [{ title: 'Agencies', href: index() }],
};
