import { Link, usePage } from '@inertiajs/react';
import { BadgeCheck, Check, LockKeyhole, ShieldCheck } from 'lucide-react';
import BrandLogo from '@/components/all/brand-logo';
import { home } from '@/routes';
import AuthPipelineCard from './auth-pipeline-card';

const POINTS = [
    'Transcripts in English and Filipino, with timestamps',
    'Titles, summaries, tags and chapters drafted for you',
    'Every record reviewed before it is published',
];

const STATS = [
    { value: 'EN · FIL', label: 'Transcripts' },
    { value: 'SHA-256', label: 'Fixity' },
    { value: '100%', label: 'Reviewed' },
];

const TRUST = [
    { icon: ShieldCheck, label: 'Role-based access' },
    { icon: LockKeyhole, label: 'Private master files' },
    { icon: BadgeCheck, label: 'Human-reviewed' },
];

/**
 * Decorative brand panel beside the login and registration forms (large screens).
 */
export default function AuthShowcase() {
    const { name } = usePage().props;

    return (
        <div className="relative hidden overflow-hidden bg-ink p-10 text-white lg:flex lg:flex-col">
            <div aria-hidden className="pointer-events-none absolute inset-0">
                <div className="absolute inset-0 bg-[linear-gradient(to_right,oklch(1_0_0/0.06)_1px,transparent_1px),linear-gradient(to_bottom,oklch(1_0_0/0.06)_1px,transparent_1px)] mask-[radial-gradient(ellipse_at_center,black_40%,transparent_85%)] bg-size-[40px_40px]" />
                <div className="absolute -top-32 -right-32 size-96 animate-aurora rounded-full bg-brand/35 blur-3xl" />
                <div className="absolute -bottom-40 -left-24 size-[26rem] animate-aurora rounded-full bg-amber-400/10 blur-3xl [animation-delay:-8s]" />
            </div>

            <div className="relative flex items-center justify-between gap-4">
                <Link
                    href={home()}
                    className="group flex items-center gap-3 font-semibold"
                >
                    <BrandLogo
                        variant="compact"
                        tone="white"
                        alt={name}
                        className="h-9 transition-transform duration-300 group-hover:-translate-y-0.5"
                    />
                </Link>
                <span className="flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-white/70">
                    <span className="relative flex size-2">
                        <span className="absolute inset-0 animate-ping rounded-full bg-emerald-400/70" />
                        <span className="relative size-2 rounded-full bg-emerald-400" />
                    </span>
                    Archive online
                </span>
            </div>

            <div className="relative my-auto w-4/5 space-y-8 py-10">
                <div className="space-y-5">
                    <h2 className="animate-in text-4xl font-semibold tracking-tight text-balance duration-700 fill-mode-both fade-in slide-in-from-bottom-4 xl:text-5xl">
                        Your recordings,{' '}
                        <span className="animate-gradient-x bg-linear-to-r from-brand-glow via-amber-200 to-brand-glow bg-size-[200%_auto] bg-clip-text text-transparent">
                            described and kept safe.
                        </span>
                    </h2>
                    <ul className="space-y-2.5 text-sm text-white/75">
                        {POINTS.map((point, index) => (
                            <li
                                key={point}
                                className="flex animate-in items-start gap-2.5 duration-700 fill-mode-both fade-in slide-in-from-left-4"
                                style={{
                                    animationDelay: `${200 + index * 120}ms`,
                                }}
                            >
                                <span className="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full bg-brand-glow/20 text-brand-glow">
                                    <Check className="size-3" />
                                </span>
                                {point}
                            </li>
                        ))}
                    </ul>
                </div>

                <div className="w-full animate-in delay-500 duration-1000 fill-mode-both fade-in slide-in-from-bottom-6">
                    <AuthPipelineCard />
                </div>

                <dl className="grid w-full animate-in grid-cols-3 gap-3 delay-700 duration-700 fill-mode-both fade-in">
                    {STATS.map((stat) => (
                        <div
                            key={stat.label}
                            className="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5"
                        >
                            <dt className="text-[11px] text-white/50">
                                {stat.label}
                            </dt>
                            <dd className="font-semibold tracking-tight">
                                {stat.value}
                            </dd>
                        </div>
                    ))}
                </dl>
            </div>

            <ul className="relative flex flex-wrap gap-x-6 gap-y-2 text-xs text-white/55">
                {TRUST.map((item) => (
                    <li key={item.label} className="flex items-center gap-1.5">
                        <item.icon className="size-3.5 text-brand-glow" />
                        {item.label}
                    </li>
                ))}
            </ul>
        </div>
    );
}
