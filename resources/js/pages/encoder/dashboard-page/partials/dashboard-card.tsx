import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    /** Short context beside the title, e.g. "2 queued · 1 running". */
    meta?: ReactNode;
    action?: { label: string; href: InertiaLinkProps['href'] };
    className?: string;
    children: ReactNode;
};

/**
 * One dashboard section: a bordered card with a title row and an optional
 * link to the full page.
 */
export default function DashboardCard({
    title,
    meta,
    action,
    className,
    children,
}: Props) {
    return (
        <section
            className={cn(
                'grid content-start gap-3 rounded-xl border bg-card p-4',
                className,
            )}
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-sm font-medium">{title}</h2>
                <div className="flex items-center gap-2">
                    {meta && (
                        <span className="text-xs text-muted-foreground tabular-nums">
                            {meta}
                        </span>
                    )}
                    {action && (
                        <Button size="sm" variant="ghost" asChild>
                            <Link href={action.href}>{action.label}</Link>
                        </Button>
                    )}
                </div>
            </div>
            {children}
        </section>
    );
}
