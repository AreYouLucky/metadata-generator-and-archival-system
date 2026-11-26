import type { ReactNode } from "react";
import { cn } from "@/lib/utils";

type Props = {
    title: string;
    icon?: ReactNode;
    /** Replaces the small caps title in the header, e.g. with tabs. */
    heading?: ReactNode;
    actions?: ReactNode;
    className?: string;
    children: ReactNode;
};

/**
 * A docked editor panel: a slim header strip with a small caps title (or
 * custom heading such as tabs), and a body that fills the remaining height.
 */
export default function EditorPanel({
    title,
    icon,
    heading,
    actions,
    className,
    children,
}: Props) {
    return (
        <section
            aria-label={title}
            className={cn("flex h-full min-h-0 flex-col", className)}
        >
            <header className="flex shrink-0 items-center justify-between gap-2 border-b border-border px-3">
                {heading ?? (
                    <h2 className="flex min-w-0 items-center gap-2 truncate text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                        {icon}
                        {title}
                    </h2>
                )}
                {actions}
            </header>
            {children}
        </section>
    );
}
