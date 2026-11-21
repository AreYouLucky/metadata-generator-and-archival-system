/**
 * Decorative background for the "How it works" fold: a dot grid, drifting
 * colour glows and dashed lines flowing across like a pipeline.
 */
export default function StepsBackdrop() {
    return (
        <div aria-hidden className="pointer-events-none absolute inset-0">
            <div className="absolute inset-0 bg-linear-to-br from-brand-muted via-muted to-amber-50 dark:to-amber-950/30" />
            <div className="absolute inset-0 bg-[radial-gradient(color-mix(in_oklch,var(--brand)_35%,transparent)_1.2px,transparent_1.6px)] mask-[radial-gradient(ellipse_at_top_right,black_25%,transparent_70%)] bg-size-[22px_22px]" />
            <div className="absolute -top-32 -left-24 size-[26rem] animate-aurora rounded-full bg-brand/20 blur-3xl" />
            <div className="absolute -right-20 -bottom-32 size-[24rem] animate-aurora rounded-full bg-amber-300/25 blur-3xl [animation-delay:-7s] dark:bg-amber-500/10" />

            <svg
                className="absolute inset-x-0 bottom-0 h-2/3 w-full text-brand"
                viewBox="0 0 1200 400"
                preserveAspectRatio="none"
                fill="none"
            >
                <path
                    d="M0 300 C 200 220, 350 380, 600 290 S 1000 200, 1200 260"
                    stroke="currentColor"
                    strokeOpacity="0.35"
                    strokeWidth="1.5"
                    strokeDasharray="6 18"
                    className="animate-dash"
                />
                <path
                    d="M0 350 C 250 280, 400 400, 650 330 S 1000 270, 1200 320"
                    stroke="currentColor"
                    strokeOpacity="0.2"
                    strokeWidth="1"
                    strokeDasharray="4 20"
                    className="animate-dash [animation-duration:5s]"
                />
                <path
                    d="M0 240 C 220 170, 420 320, 640 240 S 1020 150, 1200 200"
                    stroke="currentColor"
                    strokeOpacity="0.15"
                    strokeWidth="1"
                />
            </svg>
        </div>
    );
}
