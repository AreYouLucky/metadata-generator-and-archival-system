import { useId } from "react";
import { cn } from "@/lib/utils";

type Props = {
    className?: string;
};

/**
 * A row of film perforations, drawn with an SVG pattern so it repeats along
 * any width. Colour comes from `currentColor`.
 */
export default function FilmSprockets({ className }: Props) {
    const patternId = `sprockets-${useId().replace(/[^a-zA-Z0-9_-]/g, "")}`;

    return (
        <svg aria-hidden className={cn("block h-4 w-full", className)}>
            <defs>
                <pattern
                    id={patternId}
                    width="36"
                    height="16"
                    patternUnits="userSpaceOnUse"
                >
                    <rect
                        x="8"
                        y="1.5"
                        width="20"
                        height="13"
                        rx="3"
                        fill="currentColor"
                    />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill={`url(#${patternId})`} />
        </svg>
    );
}
