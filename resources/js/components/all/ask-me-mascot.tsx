import { useId } from 'react';
import { cn } from '@/lib/utils';

const FINGERS = [
    'M50.2 15.5 L48.6 11',
    'M52.6 14.8 L52.4 9.6',
    'M55 15.2 L56.4 10.4',
    'M56.4 17.4 L59.4 14.6',
];

type Props = {
    className?: string;
    /** Wave hello (a few waves, then a rest, on a loop). */
    waving?: boolean;
};

/**
 * The "Ask me" mascot: an instant-photo print, a memory from the archive,
 * with a face, waving hello. Decorative, so the button or heading next to
 * it carries the label. Reduced-motion users get the still pose (app.css).
 */
export default function AskMeMascot({ className, waving = true }: Props) {
    const photoGradientId = useId();

    return (
        <svg
            viewBox="0 0 64 64"
            fill="none"
            aria-hidden
            className={cn('shrink-0', className)}
        >
            <defs>
                <linearGradient
                    id={photoGradientId}
                    x1="0"
                    y1="0"
                    x2="0"
                    y2="1"
                >
                    <stop offset="0" stopColor="#7DD3FC" />
                    <stop offset="1" stopColor="#0EA5E9" />
                </linearGradient>
            </defs>
            <g
                transform="rotate(-6 32 34)"
                stroke="#0C4A6E"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            >
                <path d="M11 37 C6.5 39 5 42.5 5.5 46" />
                <circle cx="6" cy="48" r="2.8" fill="#fff" strokeWidth="2.2" />

                <rect x="11" y="12" width="32" height="42" rx="4" fill="#fff" />
                <rect
                    x="15"
                    y="16"
                    width="24"
                    height="24"
                    rx="2"
                    fill={`url(#${photoGradientId})`}
                    stroke="none"
                />
                <g stroke="none">
                    <circle cx="35" cy="20.5" r="2.2" fill="#FACC15" />
                    <ellipse
                        cx="22.5"
                        cy="27"
                        rx="1.7"
                        ry="2.3"
                        fill="#0F172A"
                    />
                    <ellipse
                        cx="31.5"
                        cy="27"
                        rx="1.7"
                        ry="2.3"
                        fill="#0F172A"
                    />
                    <circle cx="23.1" cy="26.1" r="0.65" fill="#fff" />
                    <circle cx="32.1" cy="26.1" r="0.65" fill="#fff" />
                    <circle cx="19.3" cy="31.5" r="1.8" fill="#FACC15" />
                    <circle cx="34.7" cy="31.5" r="1.8" fill="#FACC15" />
                </g>
                <path
                    d="M24 32 Q27 35 30 32"
                    stroke="#0F172A"
                    strokeWidth="1.7"
                />
                <path
                    d="M19.5 48 q2.5 -2 5 0 t5 0 t5 0"
                    strokeWidth="1.6"
                    opacity="0.55"
                />

                <g
                    className={cn(
                        'origin-bottom-left [transform-box:fill-box]',
                        waving && 'animate-hand-wave',
                    )}
                >
                    <path d="M43 35 C49.5 34 52.5 28 52.5 21" />
                    <g strokeWidth="4.4">
                        {FINGERS.map((finger) => (
                            <path key={finger} d={finger} />
                        ))}
                    </g>
                    <g stroke="#fff" strokeWidth="1.9">
                        {FINGERS.map((finger) => (
                            <path key={finger} d={finger} />
                        ))}
                    </g>
                    <circle
                        cx="52.8"
                        cy="17.6"
                        r="3.5"
                        fill="#fff"
                        strokeWidth="2.2"
                    />
                </g>
                {waving && (
                    <g strokeWidth="1.7" opacity="0.45">
                        <path d="M60.5 19 q2 2.2 1.6 5" />
                        <path d="M45 9.5 q1.2 -2.6 3.8 -3.6" />
                    </g>
                )}
            </g>
        </svg>
    );
}
