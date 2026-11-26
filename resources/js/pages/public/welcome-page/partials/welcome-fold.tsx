import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    id?: string;
    className?: string;
    children: ReactNode;
};

/**
 * One panel of the welcome page, shown as a frame of the film reel on large,
 * tall screens (see WelcomeReel) and stacked elsewhere.
 *
 * The empty anchor before the panel carries the section `id` and scroll-snap
 * point for the stacked layout. In the reel it is hidden, because the panel
 * moves sideways; WelcomeReel handles snapping and links there.
 */
export default function WelcomeFold({ id, className, children }: Props) {
    return (
        <>
            <div
                id={id}
                aria-hidden
                className="snap-start scroll-mt-20 lg:scroll-mt-24 fold:hidden"
            />
            <section
                className={cn(
                    'relative overflow-hidden rounded-3xl p-6 sm:p-10',
                    'fold:flex fold:h-full fold:flex-col fold:justify-center fold:rounded-xl fold:border-0 fold:px-14 fold:py-12 fold-short:px-10 fold-short:py-8',
                    className,
                )}
            >
                {children}
            </section>
        </>
    );
}
