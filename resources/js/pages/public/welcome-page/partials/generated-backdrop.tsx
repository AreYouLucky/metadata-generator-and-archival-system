import { Sparkles } from 'lucide-react';

const SPARKS = [
    { position: 'top-10 right-[38%]', size: 'size-4', delay: '0s' },
    { position: 'bottom-6 right-[32%]', size: 'size-3', delay: '-2s' },
    { position: 'bottom-12 left-[8%]', size: 'size-3.5', delay: '-4s' },
    { position: 'top-[45%] right-6', size: 'size-3', delay: '-1s' },
];

/**
 * Decorative background for the metadata fold: soft sky and yellow glows
 * and a few floating sparkles.
 */
export default function GeneratedBackdrop() {
    return (
        <div
            aria-hidden
            className="pointer-events-none absolute inset-0 overflow-hidden"
        >
            <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgb(186_230_253/0.55),transparent_50%)]" />
            <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_right,rgb(253_224_71/0.3),transparent_50%)]" />
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
