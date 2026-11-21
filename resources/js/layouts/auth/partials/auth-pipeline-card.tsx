import { Check, Circle, FileVideo, Loader2 } from 'lucide-react';

type StageState = 'done' | 'active' | 'pending';

const STAGES: { label: string; detail: string; state: StageState }[] = [
    { label: 'Uploaded and checksummed', detail: 'SHA-256', state: 'done' },
    { label: 'Transcribed', detail: 'English · Filipino', state: 'done' },
    {
        label: 'Drafting title, summary and chapters',
        detail: 'In progress',
        state: 'active',
    },
    { label: 'Admin review', detail: 'Before publishing', state: 'pending' },
];

/** Fixed bar heights (0–1) so the waveform looks the same on every render. */
const WAVEFORM = Array.from(
    { length: 48 },
    (_, index) =>
        0.25 + Math.abs(Math.sin(index * 0.9) * Math.cos(index * 0.37)) * 0.75,
);

/**
 * Decorative card showing a recording moving through the processing pipeline.
 */
export default function AuthPipelineCard() {
    return (
        <div
            aria-hidden
            className="relative rounded-2xl border border-white/10 bg-white/5 p-5 shadow-2xl shadow-black/30 backdrop-blur"
        >
            <div className="absolute -top-3 -right-3 animate-float rounded-lg border border-white/10 bg-ink px-2.5 py-1 text-[11px] font-medium text-brand-glow shadow-lg">
                12 tags drafted
            </div>

            <div className="flex items-center gap-3">
                <span className="flex size-9 items-center justify-center rounded-lg bg-brand/30 text-brand-glow">
                    <FileVideo className="size-4" />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium">
                        Science Week: Opening Ceremony
                    </p>
                    <p className="font-mono text-[11px] text-white/50">
                        ACC-2026-00148 · 1 h 12 min
                    </p>
                </div>
                <span className="flex items-center gap-1.5 rounded-full bg-brand-glow/15 px-2 py-0.5 text-[11px] font-medium text-brand-glow">
                    <span className="size-1.5 animate-pulse rounded-full bg-brand-glow" />
                    Processing
                </span>
            </div>

            <div className="mt-4 flex h-10 items-center gap-[3px]">
                {WAVEFORM.map((height, index) => (
                    <span
                        key={index}
                        className="w-full animate-wave rounded-full bg-linear-to-t from-brand to-brand-glow"
                        style={{
                            height: `${height * 100}%`,
                            animationDelay: `${(index % 12) * -90}ms`,
                        }}
                    />
                ))}
            </div>

            <ol className="mt-4 space-y-3">
                {STAGES.map((stage) => (
                    <li key={stage.label} className="flex items-center gap-3">
                        {stage.state === 'done' && (
                            <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-ink">
                                <Check className="size-3" />
                            </span>
                        )}
                        {stage.state === 'active' && (
                            <Loader2 className="size-5 shrink-0 animate-spin text-brand-glow" />
                        )}
                        {stage.state === 'pending' && (
                            <Circle className="size-5 shrink-0 text-white/25" />
                        )}
                        <div className="min-w-0 flex-1">
                            <div className="flex items-center justify-between gap-3 text-sm">
                                <span
                                    className={
                                        stage.state === 'pending'
                                            ? 'truncate text-white/50'
                                            : 'truncate text-white/90'
                                    }
                                >
                                    {stage.label}
                                </span>
                                <span className="shrink-0 text-[11px] text-white/45">
                                    {stage.detail}
                                </span>
                            </div>
                            {stage.state === 'active' && (
                                <div className="mt-1.5 h-1 overflow-hidden rounded-full bg-white/10">
                                    <div className="h-full animate-progress rounded-full bg-brand-glow" />
                                </div>
                            )}
                        </div>
                    </li>
                ))}
            </ol>
        </div>
    );
}
