import { cn } from "@/lib/utils";
import Reveal from "./reveal";

type Props = {
    title: string;
    description: string;
    /** `dark` on the ink frame. */
    tone?: "light" | "dark";
    /** `split` puts the description on the right, level with the title. */
    layout?: "stacked" | "split";
    className?: string;
};

/**
 * The heading every section frame opens with: the same tracked eyebrow as
 * the hero, a title and a short description.
 */
export default function FoldHeading({
    title,
    description,
    tone = "light",
    layout = "stacked",
    className,
}: Props) {
    const isDark = tone === "dark";

    return (
        <Reveal
            className={cn(
                "relative",
                layout === "split"
                    ? "grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,26rem)] lg:items-end lg:gap-16"
                    : "max-w-2xl",
                className,
            )}
        >
            <div className="space-y-3 sm:space-y-4">
                <h2 className="text-3xl leading-[1.1] font-semibold tracking-tight text-balance sm:text-4xl xl:text-[2.75rem] fold-short:text-4xl">
                    {title}
                </h2>
                {layout === "stacked" && (
                    <p
                        className={cn(
                            "text-base leading-relaxed text-pretty sm:text-lg",
                            isDark ? "text-white/70" : "text-muted-foreground",
                        )}
                    >
                        {description}
                    </p>
                )}
            </div>
            {layout === "split" && (
                <p
                    className={cn(
                        "leading-relaxed text-pretty",
                        isDark ? "text-white/70" : "text-muted-foreground",
                    )}
                >
                    {description}
                </p>
            )}
        </Reveal>
    );
}
