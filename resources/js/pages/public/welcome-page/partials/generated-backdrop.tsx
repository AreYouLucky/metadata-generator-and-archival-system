import { Sparkles } from 'lucide-react';

const SPARKS = [
    { position: 'top-10 right-[38%]', size: 'size-4', delay: '0s' },
    { position: 'bottom-6 right-[32%]', size: 'size-3', delay: '-2s' },
    { position: 'bottom-12 left-[8%]', size: 'size-3.5', delay: '-4s' },
    { position: 'top-[45%] right-6', size: 'size-3', delay: '-1s' },
];

/**
 * Decorative background for the "What gets drafted" fold: a fading grid, a
 * slowly turning colour beam behind the cards and a few floating sparkles.
 */
export default function GeneratedBackdrop() {
    return (
        <div
            aria-hidden
            className="pointer-events-none absolute inset-0 overflow-hidden"
        >
            <div className="absolute inset-0 bg-linear-to-b from-card via-card to-brand-muted/60" />
            <div className="absolute inset-0 bg-[linear-gradient(to_right,var(--border)_1px,transparent_1px),linear-gradient(to_bottom,var(--border)_1px,transparent_1px)] mask-[radial-gradient(ellipse_at_top_right,black_15%,transparent_65%)] bg-size-[44px_44px]" />
            <div className="absolute top-1/2 left-1/2 size-[44rem] -translate-x-1/2 -translate-y-1/2 animate-spin-slow rounded-full bg-[conic-gradient(from_0deg,var(--brand),transparent_25%,var(--color-yellow-400),transparent_55%,var(--brand-glow),transparent_80%)] opacity-15 blur-3xl dark:opacity-20" />
            <div className="absolute -top-24 -right-24 size-80 animate-aurora rounded-full bg-brand/15 blur-3xl" />
            <div className="absolute -bottom-28 -left-16 size-80 animate-aurora rounded-full bg-yellow-300/25 blur-3xl [animation-delay:-9s]" />

            {SPARKS.map((spark) => (
                <Sparkles
                    key={spark.position}
                    className={`absolute ${spark.position} ${spark.size} animate-float text-brand/50`}
                    style={{ animationDelay: spark.delay }}
                />
            ))}
        </div>
    );
}
