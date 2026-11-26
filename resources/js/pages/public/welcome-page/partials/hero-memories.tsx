import { Cake, GraduationCap, Landmark, Mic } from "lucide-react";

const MEMORIES = [
    {
        icon: Cake,
        caption: "Lola’s 80th",
        tile: "bg-yellow-200 text-yellow-900",
        position: "-left-28 top-0 w-32 -rotate-[9deg]",
        delay: "0s",
    },
    {
        icon: GraduationCap,
        caption: "Class of ’26",
        tile: "bg-sky-200 text-sky-900",
        position: "-left-23 top-[46%] w-28 rotate-[6deg]",
        delay: "-2s",
    },
    {
        icon: Mic,
        caption: "Founders’ day",
        tile: "bg-ink text-white",
        position: "-left-15 -bottom-15 w-24 -rotate-[4deg]",
        delay: "-4s",
    },
    {
        icon: Landmark,
        caption: "Town hall",
        tile: "bg-emerald-200 text-emerald-900",
        position: "-right-24 -top-14 w-28 rotate-[8deg]",
        delay: "-1s",
    },
];

/**
 * Instant-photo prints fanned out behind the hero player, like memories
 * pulled from the archive. Decorative; large screens only.
 */
export default function HeroMemories() {
    return (
        <div aria-hidden className="pointer-events-none hidden lg:block">
            {MEMORIES.map((memory) => (
                <div
                    key={memory.caption}
                    className={`absolute ${memory.position}`}
                >
                    <div
                        className="animate-float rounded-md border bg-white p-1.5 pb-0"
                        style={{ animationDelay: memory.delay }}
                    >
                        <div
                            className={`flex aspect-square items-center justify-center rounded-sm ${memory.tile}`}
                        >
                            <memory.icon className="size-1/3" />
                        </div>
                        <p className="truncate px-0.5 py-1.5 text-center font-mono text-[10px] text-muted-foreground">
                            {memory.caption}
                        </p>
                    </div>
                </div>
            ))}
        </div>
    );
}
