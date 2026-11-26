import type { ReactNode } from 'react';

type Props = {
    title: string;
    /** Shown on the right, e.g. a "Browse all" link. */
    action?: ReactNode;
};

/**
 * A home section's heading: a title and an optional action.
 */
export default function SectionHeading({ title, action }: Props) {
    return (
        <div className="flex flex-wrap items-end justify-between gap-3">
            <h2 className="text-2xl font-semibold tracking-tight">{title}</h2>
            {action}
        </div>
    );
}
