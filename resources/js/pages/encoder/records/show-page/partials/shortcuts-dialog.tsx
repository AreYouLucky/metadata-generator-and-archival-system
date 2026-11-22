import { Keyboard } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { SHORTCUTS } from '@/hooks/use-keyboard-shortcuts';
import { useWorkspaceStore } from '@/stores/workspace-store';

/**
 * The workspace's keyboard shortcuts, opened with "?" or the keyboard button
 * in the header.
 */
export default function ShortcutsDialog() {
    const open = useWorkspaceStore((state) => state.shortcutsOpen);
    const setOpen = useWorkspaceStore((state) => state.setShortcutsOpen);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    aria-label="Keyboard shortcuts"
                    title="Keyboard shortcuts (?)"
                >
                    <Keyboard aria-hidden />
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Keyboard shortcuts</DialogTitle>
                    <DialogDescription>
                        They work anywhere in the workspace except while you
                        type in a field.
                    </DialogDescription>
                </DialogHeader>
                <dl className="grid grid-cols-[auto_1fr] items-center gap-x-6 gap-y-2.5 text-sm">
                    {SHORTCUTS.map((shortcut) => (
                        <div key={shortcut.action} className="contents">
                            <dt className="flex gap-1">
                                {shortcut.keys.map((key) => (
                                    <kbd
                                        key={key}
                                        className="min-w-7 rounded border bg-muted px-1.5 py-0.5 text-center font-mono text-xs"
                                    >
                                        {key}
                                    </kbd>
                                ))}
                            </dt>
                            <dd className="text-muted-foreground">
                                {shortcut.action}
                            </dd>
                        </div>
                    ))}
                </dl>
            </DialogContent>
        </Dialog>
    );
}
