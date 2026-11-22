import { cn } from '@/lib/utils';
import type { Shot } from '@/types/visuals';

type Props = {
    shot: Shot;
    number: number;
    label: string;
    selected: boolean;
    onClick: () => void;
};

/** One detected shot: its number, where it starts and how long it lasts. */
export default function ShotRow({
    shot,
    number,
    label,
    selected,
    onClick,
}: Props) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={selected}
            className={cn(
                'flex w-full items-center justify-between gap-2 rounded-sm px-2 py-1 text-left text-xs transition-colors hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                selected && 'bg-brand/20 hover:bg-brand/25',
            )}
        >
            <span className="text-muted-foreground tabular-nums">
                #{number}
            </span>
            <span className="flex-1 font-mono tabular-nums">{label}</span>
            <span className="text-muted-foreground tabular-nums">
                {(shot.end - shot.start).toFixed(1)} s
            </span>
        </button>
    );
}
