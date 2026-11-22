import type { ReactNode } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    useCreateExportSchema,
    useUpdateExportSchema,
} from '@/hooks/use-export-schemas';
import { getErrorMessage } from '@/lib/utilities';
import type {
    ExportFieldGroup,
    ExportSchema,
    ExportSchemaInput,
} from '@/types/export-schemas';

type Props = {
    fieldGroups: ExportFieldGroup[];
    /** Edit this schema; create a new one when absent. */
    schema?: ExportSchema;
    trigger: ReactNode;
};

const EMPTY: ExportSchemaInput = { name: '', fields: [], is_default: false };

/**
 * Name a schema and tick the fields to keep, grouped as in the JSON export.
 * Ticking a whole block (e.g. "Whole AI block") already includes its fields.
 */
export default function SchemaFormDialog({
    fieldGroups,
    schema,
    trigger,
}: Props) {
    const [open, setOpen] = useState(false);
    const [form, setForm] = useState<ExportSchemaInput>(schema ?? EMPTY);
    const create = useCreateExportSchema();
    const update = useUpdateExportSchema();
    const saving = create.isPending || update.isPending;
    const error = create.error ?? update.error;
    const chosen = new Set(form.fields);

    const toggle = (path: string, checked: boolean) =>
        setForm((current) => ({
            ...current,
            fields: checked
                ? [...current.fields, path]
                : current.fields.filter((field) => field !== path),
        }));

    const submit = () => {
        const options = {
            onSuccess: () => {
                toast.success(schema ? 'Schema saved.' : 'Schema created.');
                setOpen(false);
            },
        };

        if (schema) {
            update.mutate({ id: schema.id, ...form }, options);
        } else {
            create.mutate(form, options);
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (next) {
                    setForm(schema ?? EMPTY);
                    create.reset();
                    update.reset();
                }
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {schema ? 'Edit export schema' : 'New export schema'}
                    </DialogTitle>
                    <DialogDescription>
                        A JSON export with this schema keeps only the fields you
                        tick (plus the schema version and export time).
                    </DialogDescription>
                </DialogHeader>

                <form
                    id="export-schema-form"
                    className="grid gap-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="schema-name">Name</Label>
                        <Input
                            id="schema-name"
                            value={form.name}
                            maxLength={100}
                            required
                            placeholder="e.g. Website listing"
                            onChange={(event) =>
                                setForm({ ...form, name: event.target.value })
                            }
                        />
                    </div>

                    <div className="grid max-h-[45vh] gap-5 overflow-y-auto pr-2 sm:grid-cols-2">
                        {fieldGroups.map((group) => (
                            <fieldset
                                key={group.group}
                                className="grid gap-1.5"
                            >
                                <legend className="mb-1 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    {group.group}
                                </legend>
                                {group.fields.map((field) => {
                                    const id = `field-${field.path}`;

                                    return (
                                        <div
                                            key={field.path}
                                            className="flex items-center gap-2"
                                        >
                                            <Checkbox
                                                id={id}
                                                checked={chosen.has(field.path)}
                                                onCheckedChange={(checked) =>
                                                    toggle(
                                                        field.path,
                                                        checked === true,
                                                    )
                                                }
                                            />
                                            <Label
                                                htmlFor={id}
                                                className="text-sm font-normal"
                                            >
                                                {field.label}
                                            </Label>
                                        </div>
                                    );
                                })}
                            </fieldset>
                        ))}
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="schema-default"
                            checked={form.is_default}
                            onCheckedChange={(checked) =>
                                setForm({
                                    ...form,
                                    is_default: checked === true,
                                })
                            }
                        />
                        <Label htmlFor="schema-default" className="font-normal">
                            Pick this schema first in the Export panel
                        </Label>
                    </div>

                    {error && <InputError message={getErrorMessage(error)} />}
                </form>

                <DialogFooter>
                    <span className="mr-auto self-center text-xs text-muted-foreground">
                        {form.fields.length} selected
                    </span>
                    <Button
                        type="submit"
                        form="export-schema-form"
                        disabled={saving || form.fields.length === 0}
                    >
                        {saving && <Spinner />}
                        {schema ? 'Save' : 'Create'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
