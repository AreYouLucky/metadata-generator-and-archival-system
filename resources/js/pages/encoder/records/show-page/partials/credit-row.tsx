import { ArrowDown, ArrowUp, X } from 'lucide-react';
import { useState } from 'react';
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
    contributor: Contributor;
    roles: ContributorRoleOption[];
    canEdit: boolean;
    disabled: boolean;
    isFirst: boolean;
    isLast: boolean;
    onChange: (contributor: Contributor) => void;
    onMove: (offset: number) => void;
    onRemove: () => void;
};

/**
 * One credit: role, name (saved on Enter or when the field loses focus),
 * move up/down and remove.
 */
export default function CreditRow({
    contributor,
    roles,
    canEdit,
    disabled,
    isFirst,
    isLast,
    onChange,
    onMove,
    onRemove,
}: Props) {
    const [name, setName] = useState(contributor.name);
    const roleLabel =
        roles.find((role) => role.value === contributor.role)?.label ??
        contributor.role;

    if (!canEdit) {
        return (
            <div className="flex items-baseline justify-between gap-3 rounded-md px-2 py-1.5 text-sm">
                <span>{contributor.name}</span>
                <span className="text-xs text-muted-foreground">
                    {roleLabel}
                </span>
            </div>
        );
    }

    const saveName = () => {
        const trimmed = name.trim();

        if (trimmed === '') {
            setName(contributor.name);

            return;
        }

        if (trimmed !== contributor.name) {
            onChange({ ...contributor, name: trimmed });
        }
    };

    return (
        <div className="group flex items-center gap-1.5">
            <Select
                value={contributor.role}
                disabled={disabled}
                onValueChange={(role) =>
                    onChange({ ...contributor, role: role as ContributorRole })
                }
            >
                <SelectTrigger
                    className="h-8 w-36 shrink-0 text-xs"
                    aria-label={`Role of ${contributor.name}`}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {roles.map((role) => (
                        <SelectItem key={role.value} value={role.value}>
                            {role.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Input
                value={name}
                maxLength={255}
                className="h-8 min-w-0 flex-1"
                aria-label={`Name (${roleLabel})`}
                onChange={(event) => setName(event.target.value)}
                onBlur={saveName}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        saveName();
                    }

                    if (event.key === 'Escape') {
                        setName(contributor.name);
                    }
                }}
            />
            <div className="flex shrink-0 opacity-60 group-hover:opacity-100 focus-within:opacity-100">
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-7"
                    disabled={disabled || isFirst}
                    onClick={() => onMove(-1)}
                    aria-label={`Move ${contributor.name} up`}
                >
                    <ArrowUp aria-hidden />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-7"
                    disabled={disabled || isLast}
                    onClick={() => onMove(1)}
                    aria-label={`Move ${contributor.name} down`}
                >
                    <ArrowDown aria-hidden />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-7 text-red-700 hover:text-red-800"
                    disabled={disabled}
                    onClick={onRemove}
                    aria-label={`Remove ${contributor.name}`}
                >
                    <X aria-hidden />
                </Button>
            </div>
        </div>
    );
}
