import { Copy, ScanText, SquareDashedMousePointer } from 'lucide-react';
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
import type { FrameRegion } from '@/hooks/use-frame-text';
import { formatTimestamp, getErrorMessage } from '@/lib/utilities';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { Contributor, ContributorRoleOption } from '@/types/records';
import FrameRegionPicker from './frame-region-picker';
import FrameTextLine from './frame-text-line';

type Props = {
    recordId: string;
    contributors: Contributor[];
    roles: ContributorRoleOption[];
    canEdit: boolean;
};

/**
 * "Copy text" on the paused frame: the encoder drags a box over the text shown
 * in the video (titles, lower-thirds, slides), it is read in the browser, and
 * they can copy it or add a name straight to the credits.
 */
export default function FrameTextTool({
    recordId,
    contributors,
    roles,
    canEdit,
}: Props) {
    const player = useWorkspaceStore((state) => state.player);
    const paused = useWorkspaceStore((state) => state.paused);
    const [picking, setPicking] = useState(false);
    const [open, setOpen] = useState(false);
    const [text, setText] = useState('');
    const [, copy] = useClipboard();
    const read = useFrameText();

    // Playing the video ends picking: the area belongs to the paused frame.
    if (picking && !paused) {
        setPicking(false);
    }

    if (!player || (!paused && !open)) {
        return null;
    }

    const pickAgain = () => {
        setOpen(false);
        setPicking(true);
    };

    const start = (region: FrameRegion | null) => {
        setPicking(false);
        setOpen(true);
        setText('');
        read.mutate(
            { video: player, region },
            {
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
            },
        );
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
            {picking ? (
                <FrameRegionPicker
                    video={player}
                    onPick={start}
                    onCancel={() => setPicking(false)}
                />
            ) : (
                <Button
                    size="sm"
                    variant="secondary"
                    onClick={() => setPicking(true)}
                    disabled={read.isPending}
                    className="absolute top-3 right-3 z-10 bg-black/70 text-white shadow-lg backdrop-blur hover:bg-black/80"
                    title="Pick the text shown in this frame"
                >
                    {read.isPending ? <Spinner /> : <ScanText aria-hidden />}
                    Copy text
                </Button>
            )}

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {result?.cropped
                                ? 'Text in the picked area'
                                : 'Text in this frame'}
                        </DialogTitle>
                        <DialogDescription>
                            {result
                                ? `Read at ${formatTimestamp(result.time)}. Check it before using it: on-screen text recognition can misread stylised fonts.`
                                : 'Reading the text…'}
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
                                    No readable text was found. Pause where the
                                    text is clear, then draw the box tightly
                                    around it and try again.
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
                        <Textarea
                            value={text}
                            onChange={(event) => setText(event.target.value)}
                            rows={4}
                            aria-label="All text read (editable)"
                        />
                    )}

                    {result && (
                        <div className="flex flex-wrap gap-2">
                            {result.lines.length > 0 && (
                                <Button
                                    variant="secondary"
                                    onClick={() => void copyText(text)}
                                    disabled={text.trim() === ''}
                                >
                                    <Copy aria-hidden />
                                    Copy all
                                </Button>
                            )}
                            <Button variant="ghost" onClick={pickAgain}>
                                <SquareDashedMousePointer aria-hidden />
                                Pick another area
                            </Button>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
