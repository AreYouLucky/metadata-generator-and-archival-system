import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/export-schemas';
import type { ExportFieldGroup, ExportSchema } from '@/types/export-schemas';
import SchemaFormDialog from './partials/schema-form-dialog';
import SchemaRow from './partials/schema-row';

type Props = {
    schemas: ExportSchema[];
    fieldGroups: ExportFieldGroup[];
};

/**
 * Settings → Export schemas: your own custom JSON exports (pick one in a
 * record's Export panel). Each user sees only their own.
 */
export default function ExportSchemas({ schemas, fieldGroups }: Props) {
    return (
        <>
            <Head title="Export schemas" />

            <h1 className="sr-only">Export schemas</h1>

            <div className="space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Export schemas"
                        description="Choose which fields a JSON export keeps, e.g. only the title, description and tags for a website."
                    />
                    <SchemaFormDialog
                        fieldGroups={fieldGroups}
                        trigger={
                            <Button size="sm">
                                <Plus aria-hidden />
                                New schema
                            </Button>
                        }
                    />
                </div>

                {schemas.length === 0 ? (
                    <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        No schemas yet. Without one, a JSON export holds the
                        whole record.
                    </p>
                ) : (
                    <ul className="grid gap-3">
                        {schemas.map((schema) => (
                            <SchemaRow
                                key={schema.id}
                                schema={schema}
                                fieldGroups={fieldGroups}
                            />
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

ExportSchemas.layout = {
    breadcrumbs: [{ title: 'Export schemas', href: index() }],
};
