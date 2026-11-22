import { Link, usePage } from '@inertiajs/react';
import RecordPreview from './record-preview';
import WelcomeFold from './welcome-fold';
import WelcomeMarquee from './welcome-marquee';
import { Sparkles } from 'lucide-react';

type Props = {
    canRegister: boolean;
};

const HIGHLIGHTS = [
    { value: 'EN · FIL', label: 'Transcripts' },
    { value: 'SHA-256', label: 'Fixity checks' },
    { value: '4 steps', label: 'From upload to publish' },
    { value: '100%', label: 'Human-reviewed' },
];

export default function WelcomeHero() {
    const { auth } = usePage().props;

    return (
        <WelcomeFold className="bg-ink text-white">
            <div aria-hidden className="pointer-events-none absolute inset-0">
                <div className="absolute inset-0 bg-[linear-gradient(to_right,oklch(1_0_0/0.05)_1px,transparent_1px),linear-gradient(to_bottom,oklch(1_0_0/0.05)_1px,transparent_1px)] mask-[radial-gradient(ellipse_at_center,black_35%,transparent_80%)] bg-size-[56px_56px]" />
                <div className="absolute -top-40 -left-24 size-[28rem] animate-aurora rounded-full bg-sky-400/30 blur-3xl" />
                <div className="absolute -right-24 -bottom-40 size-[30rem] animate-aurora rounded-full bg-yellow-400/20 blur-3xl [animation-delay:-6s]" />
                <div className="absolute top-1/3 left-1/2 size-72 animate-aurora rounded-full bg-sky-300/15 blur-3xl [animation-delay:-11s]" />
            </div>

            <div className="relative grid flex-1 items-center gap-12 lg:grid-cols-[1.1fr_1fr] lg:gap-16">
                <div className="space-y-7">
                    <p className="inline-flex animate-in items-center gap-2 py-1 text-xs font-medium text-brand-glow backdrop-blur duration-700 fill-mode-both fade-in slide-in-from-bottom-3">
                        <Sparkles className="size-3.5" />
                        Video archive and AI-assisted metadata
                    </p>

                    <h1 className="animate-in text-4xl font-semibold tracking-tight text-balance delay-100 duration-700 fill-mode-both fade-in slide-in-from-bottom-4 sm:text-5xl xl:text-6xl">
                        Every recording described, preserved and{' '}
                        <span className="animate-gradient-x bg-linear-to-r from-brand-glow via-amber-200 to-brand-glow bg-size-[200%_auto] bg-clip-text text-transparent">
                            easy to find.
                        </span>
                    </h1>

                    <p className="max-w-xl animate-in text-lg text-pretty text-white/70 delay-200 duration-700 fill-mode-both fade-in slide-in-from-bottom-4">
                        Videos are transcribed and described automatically, then
                        checked by an archivist before they are published.
                        Browse events, programmes and places with confidence.
                    </p>

                    <dl className="grid animate-in grid-cols-2 gap-4 border-t border-white/10 pt-6 delay-500 duration-700 fill-mode-both fade-in sm:grid-cols-4">
                        {HIGHLIGHTS.map((highlight) => (
                            <div key={highlight.label}>
                                <dt className="sr-only">{highlight.label}</dt>
                                <dd className="text-lg font-semibold tracking-tight">
                                    {highlight.value}
                                </dd>
                                <dd className="text-xs text-white/55">
                                    {highlight.label}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </div>

                <div className="animate-in delay-300 duration-1000 fill-mode-both zoom-in-95 fade-in">
                    <RecordPreview />
                </div>
            </div>

            <WelcomeMarquee className="relative mt-10" />
        </WelcomeFold>
    );
}
