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

export type AgencyFormValues = { name: string; acronym: string };

type Props = {
    trigger: ReactNode;
    heading: string;
    explanation: string;
    submitLabel: string;
    pending: boolean;
    initial?: AgencyFormValues;
    /** Resolves when saved; rejects with the request error. */
    onSubmit: (values: AgencyFormValues) => Promise<unknown>;
};

const EMPTY: AgencyFormValues = { name: '', acronym: '' };

/**
 * Add or rename an agency or one of its production units.
 */
export default function AgencyFormDialog({
    trigger,
    heading,
    explanation,
    submitLabel,
    pending,
    initial,
    onSubmit,
}: Props) {
    const [open, setOpen] = useState(false);
    const [values, setValues] = useState<AgencyFormValues>(EMPTY);
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
                        <DialogDescription>{explanation}</DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="agency-name">Name</Label>
                        <Input
                            id="agency-name"
                            required
                            maxLength={255}
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
                        <Label htmlFor="agency-acronym">
                            Acronym (optional)
                        </Label>
                        <Input
                            id="agency-acronym"
                            maxLength={32}
                            value={values.acronym}
                            placeholder="e.g. DOST"
                            onChange={(event) =>
                                setValues({
                                    ...values,
                                    acronym: event.target.value,
                                })
                            }
                        />
                        <InputError message={errors.acronym} />
                    </div>
                    <InputError message={errors.parent_id} />
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
