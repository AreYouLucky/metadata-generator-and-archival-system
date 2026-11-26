import {
    BadgeCheck,
    CalendarDays,
    Clock,
    MapPin,
    MessageCircleQuestion,
} from "lucide-react";
import HeroDetectChip from "./hero-detect-chip";
import HeroMemories from "./hero-memories";
import HeroVideo from "./hero-video";

const WAVEFORM = [0.4, 0.9, 0.6, 1, 0.5, 0.8, 0.35];

const FACTS = [
    { label: "Speakers", value: "2 labelled" },
    { label: "Chapters", value: "6 drafted" },
    { label: "Fixity", value: "SHA-256 ✓" },
];

/**
 * Illustration of an archived record: the playing clip, its metadata and an
 * "Ask me" answer. Purely decorative sample content.
 */
export default function RecordPreview() {
    return (
        <div
            className="relative mx-auto w-full max-w-xl lg:mx-0 lg:max-w-[36rem] rotate-3"
            aria-hidden
        >
            <HeroMemories />

            <div className="relative overflow-hidden rounded-2xl border border-sky-500 bg-white text-card-foreground">
                <div className="relative aspect-video bg-ink">
                    <HeroVideo />
                </div>

                <div className="space-y-4 bg-black p-4 text-white sm:p-5">
                    <div className="flex items-start justify-between gap-4">
                        <div className="min-w-0 space-y-1.5">
                            <p className="font-mono text-[11px] tracking-wide text-white/60">
                                ACC-2026-00148 · Wedding
                            </p>
                            <h2 className="leading-snug font-semibold">
                                Santos–Reyes Wedding: Exchange of Vows
                            </h2>
                            <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-white/60">
                                <span className="flex items-center gap-1">
                                    <MapPin className="size-3.5" /> Tagaytay
                                    City
                                </span>
                                <span className="flex items-center gap-1">
                                    <CalendarDays className="size-3.5" /> 14 Nov
                                    2026
                                </span>
                                <span className="flex items-center gap-1">
                                    <Clock className="size-3.5" /> 2 h 05 min
                                </span>
                            </div>
                        </div>
                        <span className="flex shrink-0 items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-medium text-emerald-700">
                            <BadgeCheck className="size-3.5" />
                            Approved
                        </span>
                    </div>

                    <dl className="grid grid-cols-3 divide-x divide-white/15 border-y border-white/15 text-xs">
                        {FACTS.map((fact) => (
                            <div
                                key={fact.label}
                                className="px-3 py-2.5 first:pl-0"
                            >
                                <dt className="text-white/60">{fact.label}</dt>
                                <dd className="mt-0.5 font-medium">
                                    {fact.value}
                                </dd>
                            </div>
                        ))}
                    </dl>

                    <div className="flex items-center gap-2.5 text-xs">
                        <span className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-tertiary text-tertiary-foreground">
                            <MessageCircleQuestion className="size-3.5" />
                        </span>
                        <p className="min-w-0 flex-1 truncate text-white/60">
                            <span className="font-medium text-white">
                                When are the vows?
                            </span>{" "}
                            From 42:10, right after the readings.
                        </p>
                        <span className="shrink-0 rounded-md border border-sky-200 bg-sky-50 px-1.5 py-0.5 font-mono text-[11px] font-medium text-brand">
                            ▶ 42:10
                        </span>
                    </div>
                </div>
            </div>

            <div className="absolute top-[30%] -left-8 hidden animate-float items-center gap-2.5 rounded-xl border bg-white px-3 py-2 text-xs sm:flex">
                <span className="flex h-6 items-center gap-0.5">
                    {WAVEFORM.map((height, index) => (
                        <span
                            key={index}
                            className="w-1 animate-wave rounded-full bg-brand"
                            style={{
                                height: `${height * 100}%`,
                                animationDelay: `${index * -0.15}s`,
                            }}
                        />
                    ))}
                </span>
                <span>
                    <span className="block text-[10px] font-medium tracking-wide text-muted-foreground uppercase">
                        Transcribing
                    </span>
                    <span className="block font-semibold">
                        Speaker 1 · EN / FIL
                    </span>
                </span>
            </div>

            <div className="absolute top-[40%] -right-4 hidden animate-float [animation-delay:-3s] sm:block">
                <HeroDetectChip />
            </div>
        </div>
    );
}
