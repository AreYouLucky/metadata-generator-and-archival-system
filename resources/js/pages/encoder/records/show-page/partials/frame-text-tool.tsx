import { Copy, ScanText } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useClipboard } from '@/hooks/use-clipboard';
import { useFrameText } from '@/hooks/use-frame-text';
import { formatTimestamp, getErrorMessage } from '@/lib/utilities';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { Contributor, ContributorRoleOption } from '@/types/records';
import FrameTextLine from './frame-text-line';

type Props = {
    recordId: string;
    contributors: Contributor[];
    roles: ContributorRoleOption[];
    canEdit: boolean;
};

/**
 * "Copy text" on the paused frame: reads the text shown in the video (titles,
 * lower-thirds, slides) in the browser, then lets the encoder copy it or add
 * a name straight to the credits.
 */
export default function FrameTextTool({
    recordId,
    contributors,
    roles,
    canEdit,
}: Props) {
    const player = useWorkspaceStore((state) => state.player);
    const paused = useWorkspaceStore((state) => state.paused);
    const [open, setOpen] = useState(false);
    const [text, setText] = useState('');
    const [, copy] = useClipboard();
    const read = useFrameText();

    if (!player || (!paused && !open)) {
        return null;
    }

    const start = () => {
        setOpen(true);
        setText('');
        read.mutate(player, {
            onSuccess: (result) => setText(result.text),
            onError: (error) =>
                toast.error(
                    error instanceof Error && error.message
                        ? error.message
                        : getErrorMessage(
                              error,
                              'The frame could not be read.',
                          ),
                ),
        });
    };

    const copyText = async (value: string) => {
        if (await copy(value)) {
            toast.success('Copied to the clipboard.');
        } else {
            toast.error('Copying is not available in this browser.');
        }
    };

    const result = read.data;

    return (
        <>
            <Button
                size="sm"
                variant="secondary"
                onClick={start}
                disabled={read.isPending}
                className="absolute top-3 right-3 z-10 bg-black/70 text-white shadow-lg backdrop-blur hover:bg-black/80"
                title="Read the text shown in this frame"
            >
                {read.isPending ? <Spinner /> : <ScanText aria-hidden />}
                Copy text
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Text in this frame</DialogTitle>
                        <DialogDescription>
                            {result
                                ? `Read at ${formatTimestamp(result.time)}. Check it before using it: on-screen text recognition can misread stylised fonts.`
                                : 'Reading the text in the paused frame…'}
                        </DialogDescription>
                    </DialogHeader>

                    {read.isPending && (
                        <p className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Spinner />
                            Reading… the first time also downloads the text
                            model (about 10 MB).
                        </p>
                    )}

                    {result && (
                        <div className="grid gap-4 sm:grid-cols-[minmax(0,14rem)_minmax(0,1fr)]">
                            <img
                                src={result.image}
                                alt={`Frame at ${formatTimestamp(result.time)}`}
                                className="w-full rounded-md ring-1 ring-white/10"
                            />
                            {result.lines.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No readable text was found in this frame.
                                    Pause where the text is clear and large,
                                    then try again.
                                </p>
                            ) : (
                                <ul className="max-h-64 space-y-1 overflow-y-auto">
                                    {result.lines.map((line, index) => (
                                        <li key={`${index}-${line}`}>
                                            <FrameTextLine
                                                recordId={recordId}
                                                line={line}
                                                contributors={contributors}
                                                roles={roles}
                                                canEdit={canEdit}
                                                onCopy={copyText}
                                            />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}

                    {result && result.lines.length > 0 && (
                        <div className="space-y-2">
                            <Textarea
                                value={text}
                                onChange={(event) =>
                                    setText(event.target.value)
                                }
                                rows={4}
                                aria-label="All text in the frame (editable)"
                            />
                            <Button
                                variant="secondary"
                                onClick={() => void copyText(text)}
                                disabled={text.trim() === ''}
                            >
                                <Copy aria-hidden />
                                Copy all
                            </Button>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
