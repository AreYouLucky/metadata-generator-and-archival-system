import { Crosshair } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatTimestamp, parseTimestamp } from '@/lib/utilities';
import { useWorkspaceStore } from '@/stores/workspace-store';

type Props = {
    id: string;
    label: string;
    /** The shortcut that sets it at the playhead (I or O). */
    shortcut: string;
    value: number | null;
    onChange: (seconds: number) => void;
};

/**
 * One end of a clip: type a time ("1:15", "75") or take the playhead's.
 * Shows tenths so short clips can be set precisely.
 */
export default function ClipTimeField({
    id,
    label,
    shortcut,
    value,
    onChange,
}: Props) {
    const shown = value === null ? '' : formatPrecise(value);
    const [text, setText] = useState(shown);
    const [invalid, setInvalid] = useState(false);

    // A mark set elsewhere (I / O, a quote, the transcript) replaces the text.
    useEffect(() => {
        setText(shown);
        setInvalid(false);
    }, [shown]);

    const commit = () => {
        if (text.trim() === '') {
            setText(shown);

            return;
        }

        const seconds = parseTimestamp(text);
        setInvalid(seconds === null);

        if (seconds !== null) {
            onChange(seconds);
        }
    };

    const fromPlayhead = () => {
        const player = useWorkspaceStore.getState().player;

        if (player) {
            onChange(player.currentTime);
        }
    };

    return (
        <div className="grid gap-1">
            <label htmlFor={id} className="text-xs text-muted-foreground">
                {label}
            </label>
            <div className="flex gap-1">
                <Input
                    id={id}
                    value={text}
                    placeholder="m:ss"
                    inputMode="decimal"
                    aria-invalid={invalid}
                    onChange={(event) => setText(event.target.value)}
                    onBlur={commit}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            commit();
                        }
                    }}
                    className="h-8 font-mono text-xs tabular-nums"
                />
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="size-8 shrink-0"
                    onClick={fromPlayhead}
                    aria-label={`${label} at the playhead`}
                    title={`${label} at the playhead (${shortcut})`}
                >
                    <Crosshair className="size-3.5" />
                </Button>
            </div>
        </div>
    );
}

/** "1:15.4": the usual clock plus tenths. */
function formatPrecise(seconds: number): string {
    const tenths = Math.floor((seconds * 10) % 10);

    return `${formatTimestamp(seconds)}.${tenths}`;
}
