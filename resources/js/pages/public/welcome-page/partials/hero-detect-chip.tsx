import { ScanEye } from 'lucide-react';
import { useEffect, useState } from 'react';

const DETECTED = ['bride', 'groom', 'veil', 'bouquet', 'garden'];

/**
 * Floating chip that cycles through objects "found" in the hero clip, as the
 * visual analysis would label them. Decorative; still under reduced motion.
 */
export default function HeroDetectChip() {
    const [index, setIndex] = useState(0);

    useEffect(() => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const timer = window.setInterval(
            () => setIndex((current) => (current + 1) % DETECTED.length),
            2200,
        );

        return () => window.clearInterval(timer);
    }, []);

    return (
        <div className="flex items-center gap-2.5 rounded-xl border bg-white px-3 py-2 text-xs">
            <span className="flex size-7 items-center justify-center rounded-lg bg-brand text-brand-foreground">
                <ScanEye className="size-3.5" />
            </span>
            <span>
                <span className="block text-[10px] font-medium tracking-wide text-muted-foreground uppercase">
                    Object detected
                </span>
                <span
                    key={index}
                    className="block animate-in font-semibold duration-500 fade-in slide-in-from-bottom-1"
                >
                    {DETECTED[index]}
                </span>
            </span>
        </div>
    );
}
