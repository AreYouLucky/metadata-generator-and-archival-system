import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    icon?: ReactNode;
    actions?: ReactNode;
    className?: string;
    children: ReactNode;
};

/**
 * A docked editor panel: a slim header strip with a small caps title, and a
 * body that fills the remaining height.
 */
export default function EditorPanel({
    title,
    icon,
    actions,
    className,
    children,
}: Props) {
    return (
        <section
            aria-label={title}
            className={cn('flex h-full min-h-0 flex-col', className)}
        >
            <header className="flex h-10 shrink-0 items-center justify-between gap-2 border-b border-white/10 px-3">
                <h2 className="flex items-center gap-2 text-[11px] font-semibold tracking-wider text-neutral-400 uppercase">
                    {icon}
                    {title}
                </h2>
                {actions}
            </header>
            {children}
        </section>
    );
}
