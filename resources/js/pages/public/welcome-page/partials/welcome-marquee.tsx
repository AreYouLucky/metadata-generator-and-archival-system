import { cn } from '@/lib/utils';

const SUBJECTS = [
    'Science and Technology Week',
    'Regional Budget Forum',
    'Town hall · Iloilo City',
    'Disaster preparedness drill',
    'Awarding of partners',
    'Farmers’ field day · Bukidnon',
    'Press briefing',
    'Youth innovation summit',
    'Coastal clean-up · Palawan',
    'Anniversary programme',
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
        <div
            aria-hidden
            className={cn(
                'overflow-hidden mask-[linear-gradient(to_right,transparent,black_10%,black_90%,transparent)]',
                className,
            )}
        >
            <div className="flex w-max animate-marquee gap-3 hover:[animation-play-state:paused]">
                {[...SUBJECTS, ...SUBJECTS].map((subject, index) => (
                    <span
                        key={`${subject}-${index}`}
                        className="flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-sm whitespace-nowrap text-white/70"
                    >
                        <span className="size-1.5 rounded-full bg-brand-glow" />
                        {subject}
                    </span>
                ))}
            </div>
        </div>
    );
}
