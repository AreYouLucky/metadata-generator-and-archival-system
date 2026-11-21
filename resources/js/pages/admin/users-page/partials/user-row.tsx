import { useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useUpdateUser } from '@/hooks/use-manage-users';
import type { UpdateUserInput } from '@/hooks/use-manage-users';
import { getErrorMessage } from '@/lib/utilities';
import type { UserRole } from '@/types/auth';
import type { AdminUserRow } from '@/types/users';

type Props = {
    user: AdminUserRow;
    isSelf: boolean;
    defaultDailyMinutes: number;
};

/**
 * Role, daily processing limit and active status, saved as they change. Your
 * own role and status are locked so there is always an active admin.
 */
export default function UserRow({ user, isSelf, defaultDailyMinutes }: Props) {
    const update = useUpdateUser();
    const [limit, setLimit] = useState(
        user.daily_processing_minutes_limit?.toString() ?? '',
    );

    const save = (input: UpdateUserInput) =>
        update.mutate(
            { userId: user.id, ...input },
            {
                onSuccess: (result) => toast.success(result.message),
                onError: (error) => toast.error(getErrorMessage(error)),
            },
        );

    const saveLimit = () => {
        const value = limit.trim() === '' ? null : Number(limit);

        if (
            value !== user.daily_processing_minutes_limit &&
            (value === null || Number.isInteger(value))
        ) {
            save({ daily_processing_minutes_limit: value });
        }
    };

    return (
        <tr className={user.is_active ? undefined : 'text-muted-foreground'}>
            <td className="px-4 py-3">
                <p className="font-medium text-foreground">
                    {user.name}
                    {isSelf && (
                        <span className="ml-2 text-xs font-normal text-muted-foreground">
                            (you)
                        </span>
                    )}
                </p>
                <p className="text-xs text-muted-foreground">{user.email}</p>
            </td>
            <td className="px-4 py-3">
                <Select
                    value={user.role}
                    disabled={isSelf || update.isPending}
                    onValueChange={(role) => save({ role: role as UserRole })}
                >
                    <SelectTrigger
                        className="h-8 w-32"
                        aria-label={`Role of ${user.name}`}
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="admin">Admin</SelectItem>
                        <SelectItem value="encoder">Encoder</SelectItem>
                        <SelectItem value="user">Viewer</SelectItem>
                    </SelectContent>
                </Select>
            </td>
            <td className="px-4 py-3 tabular-nums">{user.records_count}</td>
            <td className="px-4 py-3">
                {user.role === 'user' ? (
                    '—'
                ) : (
                    <Input
                        type="number"
                        min={0}
                        max={1440}
                        value={limit}
                        placeholder={`${defaultDailyMinutes} (default)`}
                        disabled={update.isPending}
                        className="h-8 w-36"
                        aria-label={`Daily processing minutes for ${user.name}`}
                        onChange={(event) => setLimit(event.target.value)}
                        onBlur={saveLimit}
                        onKeyDown={(event) =>
                            event.key === 'Enter' && saveLimit()
                        }
                    />
                )}
            </td>
            <td className="px-4 py-3">
                <div className="flex items-center gap-3">
                    <Badge variant={user.is_active ? 'secondary' : 'outline'}>
                        {user.is_active ? 'Active' : 'Deactivated'}
                    </Badge>
                    {!isSelf && (
                        <Button
                            variant="ghost"
                            size="sm"
                            disabled={update.isPending}
                            onClick={() => save({ is_active: !user.is_active })}
                        >
                            {user.is_active ? 'Deactivate' : 'Reactivate'}
                        </Button>
                    )}
                </div>
            </td>
        </tr>
    );
}
