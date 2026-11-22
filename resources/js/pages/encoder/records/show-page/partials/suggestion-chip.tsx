import { Check, Sparkles, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    label: ReactNode;
    confirmed: boolean;
    /** Where an unconfirmed entry came from, e.g. "ai". */
    source: string;
    disabled: boolean;
    onConfirm: () => void;
    onRemove: () => void;
};

/**
 * A subject or keyword. Suggestions (dashed, with a sparkle) wait for a
 * click on the tick; the cross rejects or removes one.
 */
export default function SuggestionChip({
    label,
    confirmed,
    source,
    disabled,
    onConfirm,
    onRemove,
}: Props) {
    return (
        <li
            className={cn(
                'flex items-center gap-1 rounded-full py-0.5 pr-1 pl-2.5 text-xs',
                confirmed
                    ? 'bg-muted'
                    : 'border border-dashed border-brand/60 text-foreground/80',
            )}
            title={
                confirmed
                    ? undefined
                    : `Suggested (${source}); confirm to keep it`
            }
        >
            {!confirmed && (
                <Sparkles
                    className="size-3 text-brand"
                    aria-label="Suggested"
                />
            )}
            <span>{label}</span>
            {!disabled && !confirmed && (
                <button
                    type="button"
                    onClick={onConfirm}
                    className="rounded-full p-0.5 text-emerald-700 hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    aria-label="Confirm"
                >
                    <Check className="size-3.5" aria-hidden />
                </button>
            )}
            {!disabled && (
                <button
                    type="button"
                    onClick={onRemove}
                    className="rounded-full p-0.5 text-muted-foreground hover:bg-muted hover:text-red-800 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    aria-label={confirmed ? 'Remove' : 'Reject'}
                >
                    <X className="size-3.5" aria-hidden />
                </button>
            )}
        </li>
    );
}
