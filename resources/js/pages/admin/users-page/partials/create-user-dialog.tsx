import { isAxiosError } from 'axios';
import { UserPlus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useCreateUser } from '@/hooks/use-manage-users';
import type { CreateUserInput } from '@/hooks/use-manage-users';
import { getErrorMessage } from '@/lib/utilities';

const EMPTY: CreateUserInput = {
    name: '',
    email: '',
    role: 'encoder',
    password: '',
    daily_processing_minutes_limit: null,
};

/**
 * Create a staff account. The account is verified at once; give the person
 * the password and ask them to change it after signing in.
 */
export default function CreateUserDialog({
    defaultDailyMinutes,
}: {
    defaultDailyMinutes: number;
}) {
    const [open, setOpen] = useState(false);
    const [form, setForm] = useState<CreateUserInput>(EMPTY);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const create = useCreateUser();

    const set = (changes: Partial<CreateUserInput>) =>
        setForm((current) => ({ ...current, ...changes }));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setErrors({});
        create.mutate(form, {
            onSuccess: (result) => {
                toast.success(result.message);
                setForm(EMPTY);
                setOpen(false);
            },
            onError: (error) => {
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
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <UserPlus aria-hidden />
                    New account
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>New staff account</DialogTitle>
                        <DialogDescription>
                            Encoders add and describe videos. Admins can also
                            manage users and review records.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="new-name">Name</Label>
                        <Input
                            id="new-name"
                            required
                            value={form.name}
                            onChange={(event) =>
                                set({ name: event.target.value })
                            }
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="new-email">Email</Label>
                        <Input
                            id="new-email"
                            type="email"
                            required
                            value={form.email}
                            onChange={(event) =>
                                set({ email: event.target.value.toLowerCase() })
                            }
                        />
                        <InputError message={errors.email} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="new-role">Role</Label>
                            <Select
                                value={form.role}
                                onValueChange={(role) =>
                                    set({
                                        role: role as CreateUserInput['role'],
                                    })
                                }
                            >
                                <SelectTrigger id="new-role">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="encoder">
                                        Encoder
                                    </SelectItem>
                                    <SelectItem value="admin">Admin</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors.role} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="new-limit">Daily minutes</Label>
                            <Input
                                id="new-limit"
                                type="number"
                                min={0}
                                max={1440}
                                placeholder={`${defaultDailyMinutes} (default)`}
                                value={
                                    form.daily_processing_minutes_limit ?? ''
                                }
                                onChange={(event) =>
                                    set({
                                        daily_processing_minutes_limit:
                                            event.target.value === ''
                                                ? null
                                                : Number(event.target.value),
                                    })
                                }
                            />
                            <InputError
                                message={errors.daily_processing_minutes_limit}
                            />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="new-password">Temporary password</Label>
                        <PasswordInput
                            id="new-password"
                            required
                            autoComplete="new-password"
                            value={form.password}
                            onChange={(event) =>
                                set({ password: event.target.value })
                            }
                        />
                        <InputError message={errors.password} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={create.isPending}>
                            {create.isPending && <Spinner />}
                            Create account
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
