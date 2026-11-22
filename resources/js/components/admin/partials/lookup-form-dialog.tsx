import { isAxiosError } from 'axios';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import { getErrorMessage } from '@/lib/utilities';
import type { LookupInput } from '@/hooks/use-lookups';

type Props = {
    trigger: ReactNode;
    heading: string;
    submitLabel: string;
    pending: boolean;
    initial?: LookupInput;
    /** Resolves when saved; rejects with the request error. */
    onSubmit: (values: LookupInput) => Promise<unknown>;
};

const EMPTY: LookupInput = { name: '', sort_order: 0 };

/**
 * Add or rename a genre or video type, and set its place in the list.
 */
export default function LookupFormDialog({
    trigger,
    heading,
    submitLabel,
    pending,
    initial,
    onSubmit,
}: Props) {
    const [open, setOpen] = useState(false);
    const [values, setValues] = useState<LookupInput>(EMPTY);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const changeOpen = (next: boolean) => {
        if (next) {
            setValues(initial ?? EMPTY);
            setErrors({});
        }

        setOpen(next);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setErrors({});
        onSubmit(values)
            .then(() => setOpen(false))
            .catch((error: unknown) => {
                if (
                    isAxiosError<{ errors?: Record<string, string[]> }>(
                        error,
                    ) &&
                    error.response?.data.errors
                ) {
                    setErrors(
                        Object.fromEntries(
                            Object.entries(error.response.data.errors).map(
                                ([key, messages]) => [key, messages[0]],
                            ),
                        ),
                    );
                } else {
                    toast.error(getErrorMessage(error));
                }
            });
    };

    return (
        <Dialog open={open} onOpenChange={changeOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>{heading}</DialogTitle>
                        <DialogDescription>
                            Lower numbers come first in pickers and filters.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="lookup-name">Name</Label>
                        <Input
                            id="lookup-name"
                            required
                            maxLength={100}
                            value={values.name}
                            onChange={(event) =>
                                setValues({
                                    ...values,
                                    name: event.target.value,
                                })
                            }
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="lookup-order">Position</Label>
                        <Input
                            id="lookup-order"
                            type="number"
                            min={0}
                            max={9999}
                            value={values.sort_order}
                            onChange={(event) =>
                                setValues({
                                    ...values,
                                    sort_order: Number(event.target.value) || 0,
                                })
                            }
                        />
                        <InputError message={errors.sort_order} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={pending}>
                            {pending && <Spinner />}
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
