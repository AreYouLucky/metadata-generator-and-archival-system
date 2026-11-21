import { Copy, UserPlus } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useSaveContributors } from '@/hooks/use-save-contributors';
import { getErrorMessage } from '@/lib/utilities';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { Contributor, ContributorRoleOption } from '@/types/records';

type Props = {
    recordId: string;
    line: string;
    contributors: Contributor[];
    roles: ContributorRoleOption[];
    canEdit: boolean;
    onCopy: (text: string) => Promise<void>;
};

/**
 * One line read from the frame: copy it, or add it to the credits under a
 * role (the inspector then switches to Credits to show it).
 */
export default function FrameTextLine({
    recordId,
    line,
    contributors,
    roles,
    canEdit,
    onCopy,
}: Props) {
    const save = useSaveContributors(recordId);
    const setInspectorTab = useWorkspaceStore((state) => state.setInspectorTab);
    const name = line.slice(0, 255);

    const addAs = (role: ContributorRoleOption) =>
        save.mutate([...contributors, { role: role.value, name }], {
            onSuccess: () => {
                setInspectorTab('credits');
                toast.success(`${name} added as ${role.label}.`);
            },
            onError: (error) =>
                toast.error(
                    getErrorMessage(error, 'The credit could not be added.'),
                ),
        });

    return (
        <div className="group flex items-center gap-2 rounded-md px-2 py-1 hover:bg-muted/50">
            <span className="min-w-0 flex-1 text-sm wrap-break-word select-text">
                {line}
            </span>
            <Button
                variant="ghost"
                size="icon"
                className="size-7 shrink-0"
                onClick={() => void onCopy(line)}
                aria-label={`Copy “${line}”`}
            >
                <Copy aria-hidden />
            </Button>
            {canEdit && (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7 shrink-0"
                            disabled={save.isPending}
                            aria-label={`Add “${line}” to the credits`}
                        >
                            <UserPlus aria-hidden />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuLabel>Add to credits as</DropdownMenuLabel>
                        {roles.map((role) => (
                            <DropdownMenuItem
                                key={role.value}
                                onSelect={() => addAs(role)}
                            >
                                {role.label}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            )}
        </div>
    );
}
