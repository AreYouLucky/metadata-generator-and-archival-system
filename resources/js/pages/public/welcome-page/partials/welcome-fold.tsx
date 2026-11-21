import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    id?: string;
    className?: string;
    children: ReactNode;
};

/**
 * One full-screen panel of the welcome page. On large, tall screens each fold
 * sticks below the header and the next one slides up over it; elsewhere the
 * folds simply stack.
 *
 * The empty anchor before the panel marks where the fold sits in the flow. It
 * carries the scroll-snap point and the section `id`, because snapping or
 * jumping to a sticky element uses its stuck position instead. Its negative
 * margin cancels the extra gap it adds to the parent's flex layout.
 */
export default function WelcomeFold({ id, className, children }: Props) {
    return (
        <>
            <div
                id={id}
                aria-hidden
                className="-mb-4 snap-start scroll-mt-24 lg:-mb-6"
            />
            <section
                className={cn(
                    'relative overflow-hidden rounded-3xl p-6 sm:p-10',
                    'fold:sticky fold:top-24 fold:flex fold:h-[calc(100svh-7rem)] fold:flex-col fold:justify-center fold:shadow-[0_-24px_60px_-30px_oklch(0_0_0/0.35)]',
                    className,
                )}
            >
                {children}
            </section>
        </>
    );
}
