import { Link, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';

const POINTS = [
    'Transcripts in English and Filipino, with timestamps',
    'Titles, summaries, tags and chapters drafted for you',
    'Every record reviewed before it is published',
];

const CHAPTERS = [
    { time: '00:00', title: 'Arrival of guests' },
    { time: '04:32', title: 'Opening remarks' },
    { time: '18:05', title: 'Keynote: research in the regions' },
    { time: '41:50', title: 'Awarding of partners' },
];

/**
 * Decorative brand panel beside the login and registration forms (large screens).
 */
export default function AuthShowcase() {
    const { name } = usePage().props;

    return (
        <div className="relative hidden overflow-hidden bg-ink p-10 text-white lg:flex lg:flex-col">
            <div
                aria-hidden
                className="absolute inset-0 bg-[linear-gradient(to_right,oklch(1_0_0/0.06)_1px,transparent_1px),linear-gradient(to_bottom,oklch(1_0_0/0.06)_1px,transparent_1px)] bg-[size:40px_40px]"
            />
            <div
                aria-hidden
                className="absolute -top-32 -right-32 size-96 rounded-full bg-brand/30 blur-3xl"
            />

            <Link
                href={home()}
                className="relative flex items-center gap-3 font-semibold"
            >
                <span className="flex size-9 items-center justify-center rounded-md bg-brand text-brand-foreground">
                    <AppLogoIcon className="size-5 fill-current" />
                </span>
                {name}
            </Link>

            <div className="relative mt-auto space-y-8">
                <div className="max-w-md space-y-4">
                    <h2 className="text-3xl font-semibold tracking-tight text-balance">
                        Your recordings, described and kept safe.
                    </h2>
                    <ul className="space-y-2.5 text-sm text-white/75">
                        {POINTS.map((point) => (
                            <li
                                key={point}
                                className="flex items-start gap-2.5"
                            >
                                <Check className="mt-0.5 size-4 shrink-0 text-brand" />
                                {point}
                            </li>
                        ))}
                    </ul>
                </div>

                <div
                    aria-hidden
                    className="max-w-md rounded-xl border border-white/10 bg-white/5 p-4 backdrop-blur"
                >
                    <p className="text-xs font-medium tracking-wide text-white/60 uppercase">
                        Suggested chapters
                    </p>
                    <ol className="mt-3 space-y-2 text-sm">
                        {CHAPTERS.map((chapter) => (
                            <li key={chapter.time} className="flex gap-3">
                                <span className="font-mono text-brand">
                                    {chapter.time}
                                </span>
                                <span className="text-white/85">
                                    {chapter.title}
                                </span>
                            </li>
                        ))}
                    </ol>
                </div>
            </div>
        </div>
    );
}
