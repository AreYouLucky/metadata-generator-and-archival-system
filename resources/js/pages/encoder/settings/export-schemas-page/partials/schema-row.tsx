import { Pencil, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useDeleteExportSchema } from '@/hooks/use-export-schemas';
import { getErrorMessage } from '@/lib/utilities';
import type { ExportFieldGroup, ExportSchema } from '@/types/export-schemas';
import SchemaFormDialog from './schema-form-dialog';

type Props = {
    schema: ExportSchema;
    fieldGroups: ExportFieldGroup[];
};

/** One schema: its name, default badge, fields, edit and delete. */
export default function SchemaRow({ schema, fieldGroups }: Props) {
    const remove = useDeleteExportSchema();
    const labels = new Map(
        fieldGroups.flatMap((group) =>
            group.fields.map((field) => [field.path, field.label] as const),
        ),
    );

    return (
        <li className="grid gap-2 rounded-lg border p-4">
            <div className="flex items-center gap-2">
                <p className="min-w-0 flex-1 truncate font-medium">
                    {schema.name}
                </p>
                {schema.is_default && (
                    <Badge variant="secondary">Default</Badge>
                )}
                <SchemaFormDialog
                    fieldGroups={fieldGroups}
                    schema={schema}
                    trigger={
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            aria-label={`Edit ${schema.name}`}
                        >
                            <Pencil aria-hidden />
                        </Button>
                    }
                />
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8 text-destructive"
                    disabled={remove.isPending}
                    aria-label={`Delete ${schema.name}`}
                    onClick={() => {
                        if (
                            window.confirm(
                                `Delete the "${schema.name}" schema?`,
                            )
                        ) {
                            remove.mutate(schema.id, {
                                onSuccess: () =>
                                    toast.success('Schema deleted.'),
                                onError: (error) =>
                                    toast.error(getErrorMessage(error)),
                            });
                        }
                    }}
                >
                    <Trash2 aria-hidden />
                </Button>
            </div>
            <p className="text-sm text-muted-foreground">
                {schema.fields
                    .map((field) => labels.get(field) ?? field)
                    .join(' · ')}
            </p>
        </li>
    );
}
