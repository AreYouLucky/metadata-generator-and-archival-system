import { Plus } from 'lucide-react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useCreateLookup } from '@/hooks/use-lookups';
import type { LookupItem, LookupKind } from '@/types/lookups';
import LookupFormDialog from './partials/lookup-form-dialog';
import LookupRow from './partials/lookup-row';

type Props = {
    kind: LookupKind;
    items: LookupItem[];
    title: string;
    description: string;
    /** Singular, lower case: "genre", "video type". */
    noun: string;
};

/**
 * An admin-managed lookup list (genres, video types): add, rename, reorder,
 * and delete while unused.
 */
export default function LookupManager({
    kind,
    items,
    title,
    description,
    noun,
}: Props) {
    const create = useCreateLookup(kind);

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <Heading title={title} description={description} />
                <LookupFormDialog
                    trigger={
                        <Button>
                            <Plus aria-hidden />
                            New {noun}
                        </Button>
                    }
                    heading={`New ${noun}`}
                    submitLabel={`Add ${noun}`}
                    pending={create.isPending}
                    onSubmit={(values) =>
                        create
                            .mutateAsync(values)
                            .then(() => toast.success(`Added ${values.name}.`))
                    }
                />
            </div>

            {items.length === 0 ? (
                <div className="rounded-xl border border-dashed p-10 text-center">
                    <p className="font-medium">Nothing here yet.</p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Encoders pick from this list in the Archive panel.
                    </p>
                </div>
            ) : (
                <ul className="divide-y rounded-xl border">
                    {items.map((item) => (
                        <LookupRow
                            key={item.id}
                            kind={kind}
                            item={item}
                            noun={noun}
                        />
                    ))}
                </ul>
            )}
        </div>
    );
}
