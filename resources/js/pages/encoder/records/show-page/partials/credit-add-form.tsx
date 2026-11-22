import { Plus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    Contributor,
    ContributorRole,
    ContributorRoleOption,
} from '@/types/records';

type Props = {
    roles: ContributorRoleOption[];
    disabled: boolean;
    onAdd: (contributor: Contributor) => void;
};

/**
 * Add a person to the credits: pick a role (cast by default), type the name,
 * press Enter. The role stays selected for the next name.
 */
export default function CreditAddForm({ roles, disabled, onAdd }: Props) {
    const [role, setRole] = useState<ContributorRole>('cast');
    const [name, setName] = useState('');

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const trimmed = name.trim();

        if (trimmed === '') {
            return;
        }

        onAdd({ role, name: trimmed });
        setName('');
    };

    return (
        <form
            onSubmit={submit}
            className="space-y-2 rounded-lg border border-border bg-white/[0.02] p-2"
        >
            <p className="text-xs font-medium text-muted-foreground">
                Add a credit
            </p>
            <div className="flex items-center gap-1.5">
                <Select
                    value={role}
                    onValueChange={(value) => setRole(value as ContributorRole)}
                >
                    <SelectTrigger
                        className="h-8 w-36 shrink-0 text-xs"
                        aria-label="Role"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {roles.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Input
                    value={name}
                    maxLength={255}
                    placeholder="Full name"
                    aria-label="Name"
                    className="h-8 min-w-0 flex-1"
                    onChange={(event) => setName(event.target.value)}
                />
                <Button
                    type="submit"
                    size="icon"
                    className="size-8 shrink-0"
                    disabled={disabled || name.trim() === ''}
                    aria-label="Add credit"
                >
                    <Plus aria-hidden />
                </Button>
            </div>
        </form>
    );
}
