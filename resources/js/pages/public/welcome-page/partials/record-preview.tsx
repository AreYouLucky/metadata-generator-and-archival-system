import {
    CalendarDays,
    Clock,
    Languages,
    MapPin,
    Play,
    ShieldCheck,
} from 'lucide-react';

const TRANSCRIPT = [
    {
        time: '00:12:41',
        speaker: 'S1',
        text: 'Good morning, and welcome to this year’s Regional Science and Technology Week.',
    },
    {
        time: '00:12:47',
        speaker: 'S2',
        text: 'Magandang umaga po sa ating mga bisita mula sa iba’t ibang probinsya.',
    },
];

const TAGS = ['science week', 'opening ceremony', 'innovation', 'Cebu'];

/**
 * Illustration of an archived record: a frame, its metadata and transcript.
 * Purely decorative sample content.
 */
export default function RecordPreview() {
    return (
        <div className="relative mx-auto w-full max-w-lg" aria-hidden>
            <div className="absolute -inset-4 rounded-3xl bg-brand/25 blur-2xl" />

            <div className="relative overflow-hidden rounded-2xl border bg-card text-card-foreground shadow-2xl shadow-black/30">
                <div className="relative aspect-video bg-ink">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_30%_35%,rgb(14_165_233/0.45),transparent_55%),radial-gradient(circle_at_75%_70%,rgb(250_204_21/0.3),transparent_50%)]" />
                    <div className="absolute inset-x-0 top-0 h-1/4 animate-scan bg-linear-to-b from-transparent via-brand-glow/20 to-transparent" />
                    <div className="absolute inset-x-0 top-0 flex justify-between p-3 font-mono text-[11px] text-white/80">
                        <span className="flex items-center gap-1.5">
                            <span className="size-1.5 rounded-full bg-red-500" />
                            MASTER · 1920×1080 · 29.97 fps
                        </span>
                        <span>00:12:47:08</span>
                    </div>
                    <div className="absolute inset-0 flex items-center justify-center">
                        <span className="relative flex size-12 items-center justify-center rounded-full bg-white/15 text-white ring-1 ring-white/30 backdrop-blur">
                            <span className="absolute inset-0 animate-ping rounded-full bg-white/20 [animation-duration:2.5s]" />
                            <Play className="size-5 translate-x-0.5 fill-current" />
                        </span>
                    </div>
                    <div className="absolute inset-x-3 bottom-3 h-1 overflow-hidden rounded-full bg-white/20">
                        <div className="h-full animate-progress rounded-full bg-brand-glow [animation-duration:12s]" />
                    </div>
                </div>

                <div className="space-y-4 p-5">
                    <div className="space-y-2">
                        <p className="font-mono text-[11px] tracking-wide text-muted-foreground">
                            ACC-2026-00148
                        </p>
                        <h2 className="leading-snug font-semibold">
                            Regional Science and Technology Week: Opening
                            Ceremony
                        </h2>
                        <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                            <span className="flex items-center gap-1">
                                <MapPin className="size-3.5" /> Cebu City,
                                Region VII
                            </span>
                            <span className="flex items-center gap-1">
                                <CalendarDays className="size-3.5" /> 14 Nov
                                2026
                            </span>
                            <span className="flex items-center gap-1">
                                <Clock className="size-3.5" /> 1 h 12 min
                            </span>
                        </div>
                    </div>

                    <ul className="space-y-2 rounded-lg bg-muted/60 p-3 text-xs">
                        {TRANSCRIPT.map((line) => (
                            <li key={line.time} className="flex gap-2.5">
                                <span className="shrink-0 font-mono text-brand">
                                    {line.time}
                                </span>
                                <span className="shrink-0 font-medium text-muted-foreground">
                                    {line.speaker}
                                </span>
                                <span className="text-foreground/90">
                                    {line.text}
                                </span>
                            </li>
                        ))}
                    </ul>

                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap gap-1.5">
                            {TAGS.map((tag) => (
                                <span
                                    key={tag}
                                    className="rounded-md border px-2 py-0.5 text-[11px] text-muted-foreground"
                                >
                                    {tag}
                                </span>
                            ))}
                        </div>
                        <span className="flex items-center gap-1 text-[11px] font-medium text-emerald-700 dark:text-emerald-400">
                            <ShieldCheck className="size-3.5" /> SHA-256
                            verified
                        </span>
                    </div>
                </div>
            </div>

            <div className="absolute -top-4 -right-3 hidden animate-float items-center gap-2 rounded-xl border bg-card px-3 py-2 text-xs font-medium text-foreground shadow-lg sm:flex">
                <span className="flex size-6 items-center justify-center rounded-md bg-brand-muted text-brand">
                    <Languages className="size-3.5" />
                </span>
                Transcribed · EN / FIL
            </div>

            <div className="absolute -bottom-5 -left-3 hidden w-52 animate-float rounded-xl border bg-card p-3 text-foreground shadow-lg [animation-delay:-3s] sm:block">
                <div className="flex items-center justify-between text-xs">
                    <span className="font-medium">Generating chapters</span>
                    <span className="flex size-2 animate-pulse rounded-full bg-brand" />
                </div>
                <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                    <div className="h-full animate-progress rounded-full bg-brand" />
                </div>
            </div>
        </div>
    );
}
