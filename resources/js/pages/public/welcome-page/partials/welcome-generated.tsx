import {
    Check,
    Circle,
    FileText,
    Images,
    ListTree,
    ScrollText,
} from "lucide-react";
import FoldHeading from "./fold-heading";
import Reveal from "./reveal";
import WelcomeFold from "./welcome-fold";

const TRANSCRIPT = [
    {
        time: "00:18:05",
        speaker: "S1",
        text: "Happy 80th birthday, Lola! Thank you for every Sunday lunch and every story.",
    },
    {
        time: "00:18:12",
        speaker: "S2",
        text: "Maligayang kaarawan po, Lola. Mahal na mahal ka namin.",
    },
    {
        time: "00:18:31",
        speaker: "S1",
        text: "Now let’s hear from the grandchildren who flew in all the way from Davao.",
    },
];

const CHAPTERS = [
    { time: "00:00", title: "Guests arrive" },
    { time: "04:32", title: "Opening prayer" },
    { time: "18:05", title: "Wishes from the grandchildren" },
    { time: "41:50", title: "Cake and candles" },
];

const TAGS = ["birthday", "family", "80th", "Cebu City", "reunion"];

const PRESETS = ["YouTube", "TikTok", "Instagram", "LinkedIn"];

const OBJECTS = ["cake", "balloons", "banner"];

const REVIEW = [
    { label: "Title and summary", done: true },
    { label: "Rights and consent", done: true },
    { label: "Security classification", done: false },
];

/**
 * The metadata drafted for every video, shown as a bento of sample cards.
 */
export default function WelcomeGenerated() {
    return (
        <WelcomeFold
            id="metadata"
            className="border bg-linear-to-b from-white via-white to-yellow-50"
        >
            <FoldHeading
                title="Hours of footage, described in minutes"
                description="Transcripts, summaries, chapters, tags and frame notes are drafted for you. Nothing goes public until a person signs off."
                layout="split"
            />

            <div
                aria-hidden
                className="relative mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:mt-10 lg:grid-cols-3 lg:gap-5 fold-short:mt-6"
            >
                <Reveal className="sm:col-span-2">
                    <div className="h-full rounded-2xl border bg-white/85 p-5 shadow-sm backdrop-blur-md flex items-center">
                        <div className="h-full w-full">
                            <div className="flex items-center justify-between">
                                <span className="flex items-center gap-2 text-sm font-medium">
                                    <ScrollText className="size-4 text-brand" />
                                    Transcript
                                    <span className="font-normal text-muted-foreground">
                                        · 2 speakers
                                    </span>
                                </span>
                                <span className="flex gap-1.5 text-[11px] font-medium">
                                    <span className="rounded-md bg-sky-700 px-2 py-0.5 text-white">
                                        EN
                                    </span>
                                    <span className="rounded-md bg-amber-700 px-2 py-0.5 text-amber-50">
                                        FIL
                                    </span>
                                </span>
                            </div>
                            <ul className="mt-4 space-y-2.5 text-sm">
                                {TRANSCRIPT.map((line, index) => (
                                    <li key={line.time}>
                                        <Reveal
                                            delay={250 + index * 220}
                                            className="flex gap-3"
                                        >
                                            <span className="shrink-0 font-mono text-xs leading-5 text-brand">
                                                {line.time}
                                            </span>
                                            <span className="shrink-0 text-xs leading-5 font-medium text-muted-foreground">
                                                {line.speaker}
                                            </span>
                                            <span className="text-foreground/90">
                                                {line.text}
                                                {index ===
                                                    TRANSCRIPT.length - 1 && (
                                                    <span className="ml-1 inline-block h-4 w-1.5 translate-y-0.5 animate-pulse rounded-sm bg-brand" />
                                                )}
                                            </span>
                                        </Reveal>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                </Reveal>

                <Reveal delay={120}>
                    <div className="flex h-full flex-col rounded-2xl border bg-white/85 p-5 shadow-sm backdrop-blur-md">
                        <span className="flex items-center gap-2 text-sm font-medium">
                            <FileText className="size-4 text-brand" />
                            Summary and tags
                        </span>
                        <p className="mt-3 text-sm leading-relaxed text-muted-foreground">
                            Four generations gather in Cebu City to celebrate
                            Lola Remy’s 80th birthday with heartfelt wishes, old
                            photographs and a candle-lit cake.
                        </p>
                        <div className="mt-3 flex flex-wrap gap-1.5">
                            {TAGS.map((tag) => (
                                <span
                                    key={tag}
                                    className="rounded-lg border px-2 py-0.5 text-xs text-muted-foreground"
                                >
                                    #{tag}
                                </span>
                            ))}
                        </div>
                        <div className="mt-auto flex flex-wrap items-center gap-1.5 pt-4 text-[11px] text-muted-foreground">
                            Tuned for
                            {PRESETS.map((preset) => (
                                <span
                                    key={preset}
                                    className="rounded-full bg-muted px-2 py-0.5 font-medium text-foreground/80"
                                >
                                    {preset}
                                </span>
                            ))}
                        </div>
                    </div>
                </Reveal>

                <Reveal delay={200}>
                    <div className="h-full rounded-2xl border bg-white/85 p-5 shadow-sm backdrop-blur-md">
                        <span className="flex items-center gap-2 text-sm font-medium">
                            <Images className="size-4 text-brand" />
                            Key frames
                        </span>
                        <div className="mt-4 flex gap-3">
                            <div className="relative aspect-video w-28 shrink-0 overflow-hidden rounded-lg bg-ink">
                                <div className="absolute top-2 left-3 size-8 rounded-full bg-tertiary" />
                                <div className="absolute right-0 bottom-0 h-1/2 w-3/5 rounded-tl-lg bg-sky-400" />
                                <span className="absolute bottom-1 left-1 rounded bg-black/50 px-1 font-mono text-[10px] text-white">
                                    41:52
                                </span>
                            </div>
                            <div className="min-w-0 space-y-1.5 text-xs">
                                <p className="truncate font-mono text-[11px] text-foreground/80">
                                    “HAPPY 80TH BIRTHDAY LOLA”
                                </p>
                                <div className="flex flex-wrap gap-1">
                                    {OBJECTS.map((object) => (
                                        <span
                                            key={object}
                                            className="rounded-md bg-sky-50 px-1.5 py-0.5 text-[11px] text-brand"
                                        >
                                            {object}
                                        </span>
                                    ))}
                                </div>
                                <p className="text-[11px] text-muted-foreground">
                                    6 faces · no recognition
                                </p>
                            </div>
                        </div>
                    </div>
                </Reveal>

                <Reveal delay={280}>
                    <div className="h-full rounded-2xl border bg-white/85 p-5 shadow-sm backdrop-blur-md">
                        <span className="flex items-center gap-2 text-sm font-medium">
                            <ListTree className="size-4 text-brand" />
                            Chapters
                        </span>
                        <ol className="mt-4 space-y-2 border-l pl-4 text-sm">
                            {CHAPTERS.map((chapter) => (
                                <li
                                    key={chapter.time}
                                    className="relative flex gap-3"
                                >
                                    <span className="absolute top-1.5 -left-[1.31rem] size-2 rounded-full bg-brand ring-4 ring-background" />
                                    <span className="font-mono text-xs leading-5 text-brand">
                                        {chapter.time}
                                    </span>
                                    <span className="truncate">
                                        {chapter.title}
                                    </span>
                                </li>
                            ))}
                        </ol>
                    </div>
                </Reveal>

                <Reveal delay={360}>
                    <div className="h-full rounded-2xl border bg-white/85 p-5 shadow-sm backdrop-blur-md">
                        <span className="flex items-center gap-2 text-sm font-medium">
                            <Check className="size-4 text-brand" />
                            Admin review
                        </span>
                        <ul className="mt-4 space-y-2.5 text-sm">
                            {REVIEW.map((item) => (
                                <li
                                    key={item.label}
                                    className="flex items-center gap-2.5"
                                >
                                    {item.done ? (
                                        <span className="flex size-5 items-center justify-center rounded-full bg-emerald-600 text-white">
                                            <Check className="size-3" />
                                        </span>
                                    ) : (
                                        <Circle className="size-5 text-muted-foreground/50" />
                                    )}
                                    <span
                                        className={
                                            item.done
                                                ? ""
                                                : "text-muted-foreground"
                                        }
                                    >
                                        {item.label}
                                    </span>
                                </li>
                            ))}
                        </ul>
                        <span className="mt-4 inline-flex w-fit items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-medium text-amber-800">
                            <span className="size-1.5 animate-pulse rounded-full bg-current" />
                            Draft · awaiting review
                        </span>
                    </div>
                </Reveal>
            </div>
        </WelcomeFold>
    );
}
