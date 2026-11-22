import { Plus } from 'lucide-react';
import { useState } from 'react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useCreateApiToken } from '@/hooks/use-api-tokens';
import { getErrorMessage } from '@/lib/utilities';
import type { ApiTokenAbilityOption, ApiTokenInput } from '@/types/api-tokens';
import SecretReveal from './secret-reveal';

type Props = {
    abilities: ApiTokenAbilityOption[];
    expiryDays: number[];
};

const NEVER = 'never';

/**
 * Name a token, tick what it may do and choose when it expires. Its text is
 * shown once, here, after it is created.
 */
export default function CreateTokenDialog({ abilities, expiryDays }: Props) {
    const [open, setOpen] = useState(false);
    const [form, setForm] = useState<ApiTokenInput>({
        name: '',
        abilities: ['records:read'],
        expires_in_days: expiryDays.includes(90) ? 90 : null,
    });
    const create = useCreateApiToken();
    const created = create.data?.plain_text;

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (next) {
                    create.reset();
                    setForm((current) => ({ ...current, name: '' }));
                }
            }}
        >
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus aria-hidden />
                    New token
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>New API token</DialogTitle>
                    <DialogDescription>
                        The token works with your own permissions: it sees only
                        the records you can open.
                    </DialogDescription>
                </DialogHeader>

                {created ? (
                    <SecretReveal label="Your token" value={created} />
                ) : (
                    <form
                        id="api-token-form"
                        className="grid gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            create.mutate(form);
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="token-name">Name</Label>
                            <Input
                                id="token-name"
                                value={form.name}
                                maxLength={100}
                                required
                                placeholder="e.g. Newsroom uploader"
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        name: event.target.value,
                                    })
                                }
                            />
                        </div>

                        <fieldset className="grid gap-2">
                            <legend className="mb-1 text-sm font-medium">
                                It may
                            </legend>
                            {abilities.map((ability) => (
                                <div
                                    key={ability.value}
                                    className="flex items-center gap-2"
                                >
                                    <Checkbox
                                        id={`ability-${ability.value}`}
                                        checked={form.abilities.includes(
                                            ability.value,
                                        )}
                                        onCheckedChange={(checked) =>
                                            setForm({
                                                ...form,
                                                abilities:
                                                    checked === true
                                                        ? [
                                                              ...form.abilities,
                                                              ability.value,
                                                          ]
                                                        : form.abilities.filter(
                                                              (value) =>
                                                                  value !==
                                                                  ability.value,
                                                          ),
                                            })
                                        }
                                    />
                                    <Label
                                        htmlFor={`ability-${ability.value}`}
                                        className="font-normal"
                                    >
                                        {ability.label}
                                    </Label>
                                </div>
                            ))}
                        </fieldset>

                        <div className="grid gap-2">
                            <Label htmlFor="token-expiry">Expires</Label>
                            <Select
                                value={
                                    form.expires_in_days === null
                                        ? NEVER
                                        : String(form.expires_in_days)
                                }
                                onValueChange={(value) =>
                                    setForm({
                                        ...form,
                                        expires_in_days:
                                            value === NEVER
                                                ? null
                                                : Number(value),
                                    })
                                }
                            >
                                <SelectTrigger id="token-expiry">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {expiryDays.map((days) => (
                                        <SelectItem
                                            key={days}
                                            value={String(days)}
                                        >
                                            In {days} days
                                        </SelectItem>
                                    ))}
                                    <SelectItem value={NEVER}>Never</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {create.error && (
                            <InputError
                                message={getErrorMessage(create.error)}
                            />
                        )}
                    </form>
                )}

                <DialogFooter>
                    {created ? (
                        <Button type="button" onClick={() => setOpen(false)}>
                            Done
                        </Button>
                    ) : (
                        <Button
                            type="submit"
                            form="api-token-form"
                            disabled={
                                create.isPending ||
                                form.abilities.length === 0 ||
                                form.name.trim() === ''
                            }
                        >
                            {create.isPending && <Spinner />}
                            Create token
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
