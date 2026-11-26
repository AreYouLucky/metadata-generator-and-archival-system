/**
 * Decorative background for the "How it works" fold: soft sky and yellow
 * glows, and dashed lines flowing across like a pipeline.
 */
export default function StepsBackdrop() {
    return (
        <div aria-hidden className="pointer-events-none absolute inset-0">
            <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgb(125_211_252/0.45),transparent_55%)]" />
            <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_left,rgb(254_240_138/0.4),transparent_45%)]" />
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
