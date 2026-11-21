import type { ReactNode } from 'react';
import { useInView } from '@/hooks/use-in-view';
import { cn } from '@/lib/utils';

type Props = {
    children: ReactNode;
    /** Milliseconds to wait after scrolling into view, for staggering. */
    delay?: number;
    className?: string;
};

/**
 * Fades and lifts its children in the first time they scroll into view.
 */
export default function Reveal({ children, delay = 0, className }: Props) {
    const { ref, isInView } = useInView<HTMLDivElement>();

    return (
        <div
            ref={ref}
            data-visible={isInView}
            style={{ transitionDelay: `${delay}ms` }}
            className={cn(
                'transition-[opacity,translate] duration-700 ease-out data-[visible=false]:translate-y-6 data-[visible=false]:opacity-0',
                className,
            )}
        >
            {children}
        </div>
    );
}
