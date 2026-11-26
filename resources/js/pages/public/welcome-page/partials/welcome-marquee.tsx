import { cn } from '@/lib/utils';

const SUBJECTS = [
    'Quarterly town hall meeting',
    'Birthday wishes for Lola Remy',
    'Commencement speech · Class of 2026',
    'Annual awards night program',
    'Santos–Reyes wedding',
    'Board meeting · Budget review',
    'Product launch keynote',
    'Family reunion · Bohol',
    'Founders’ day program',
    'Leadership forum panel',
    'Town fiesta · Iloilo City',
    'Retirement speech for Ms. Cruz',
];

type Props = {
    className?: string;
};

/**
 * Endless ribbon of sample subjects. The list is rendered twice so the
 * animation can loop without a gap; it pauses on hover.
 */
export default function WelcomeMarquee({ className }: Props) {
    return (
        <div aria-hidden className={cn('overflow-hidden', className)}>
            <div className="flex w-max animate-marquee gap-3 hover:paused">
                {[...SUBJECTS, ...SUBJECTS].map((subject, index) => (
                    <span
                        key={`${subject}-${index}`}
                        className="flex items-center gap-2 rounded-full border bg-background px-4 py-1.5 text-sm whitespace-nowrap text-muted-foreground"
                    >
                        <span className="size-1.5 rounded-full bg-tertiary" />
                        {subject}
                    </span>
                ))}
            </div>
        </div>
    );
}
