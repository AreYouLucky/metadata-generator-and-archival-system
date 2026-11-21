import { router } from '@inertiajs/react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatEnumLabel } from '@/lib/utilities';
import { index } from '@/routes/admin/logs';
import type { LogFilters, LogType } from '@/types/logs';

const ALL = 'all';

type Props = {
    filters: LogFilters;
    /** The actions the current log can hold. */
    actions: string[];
};

/**
 * Switch between the audit and access logs, and filter by action. Switching
 * logs clears the action, as each log has its own.
 */
export default function LogsFilters({ filters, actions }: Props) {
    const visit = (next: LogFilters) =>
        router.get(
            index.url(),
            {
                type: next.type,
                ...(next.action ? { action: next.action } : {}),
            },
            { preserveScroll: true, replace: true },
        );

    return (
        <div className="flex flex-wrap items-center gap-3">
            <ToggleGroup
                type="single"
                variant="outline"
                value={filters.type}
                onValueChange={(value) =>
                    value && visit({ type: value as LogType, action: null })
                }
                aria-label="Log"
            >
                <ToggleGroupItem value="audit" className="px-3">
                    Archive activity
                </ToggleGroupItem>
                <ToggleGroupItem value="access" className="px-3">
                    Views & downloads
                </ToggleGroupItem>
            </ToggleGroup>
            <Select
                value={filters.action ?? ALL}
                onValueChange={(value) =>
                    visit({
                        type: filters.type,
                        action: value === ALL ? null : value,
                    })
                }
            >
                <SelectTrigger className="w-52" aria-label="Action">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>All actions</SelectItem>
                    {actions.map((action) => (
                        <SelectItem key={action} value={action}>
                            {formatEnumLabel(action)}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
