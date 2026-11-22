import { ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

type Props = {
    direction: 'left' | 'right';
    onClick: () => void;
};

/**
 * An arrow over one end of the inspector's tab strip, fading the tabs under
 * it, shown only while more tabs are hidden on that side. Mouse only: the
 * tabs themselves are keyboard reachable and scroll into view on focus.
 */
export default function TabScrollButton({ direction, onClick }: Props) {
    const Icon = direction === 'left' ? ChevronLeft : ChevronRight;

    return (
        <button
            type="button"
            tabIndex={-1}
            onClick={onClick}
            aria-label={
                direction === 'left' ? 'Show earlier tabs' : 'Show more tabs'
            }
            className={cn(
                'absolute inset-y-0 z-10 flex w-7 items-center text-neutral-400 hover:text-foreground',
                direction === 'left'
                    ? 'left-0 justify-start bg-linear-to-r from-neutral-900 from-50% to-transparent'
                    : 'right-0 justify-end bg-linear-to-l from-neutral-900 from-50% to-transparent',
            )}
        >
            <Icon className="size-4" aria-hidden />
        </button>
    );
}
