import {
    Check,
    FileLock2,
    Fingerprint,
    History,
    Loader2,
    Scale,
} from 'lucide-react';
import Reveal from './reveal';
import WelcomeFold from './welcome-fold';

const FEATURES = [
    {
        icon: Fingerprint,
        title: 'Fixity checks',
        text: 'MD5 and SHA-256 checksums are recorded on upload and re-checked on a schedule.',
    },
    {
        icon: Scale,
        title: 'Rights and embargoes',
        text: 'Copyright, licence, release consent and embargo dates travel with each record.',
    },
    {
        icon: History,
        title: 'Provenance and audit trail',
        text: 'Where a recording came from, and every action taken on it, is kept permanently.',
    },
    {
        icon: FileLock2,
        title: 'Classification and retention',
        text: 'Restricted material never reaches the public catalogue, and disposal dates are tracked.',
    },
];

const FIXITY_LOG = [
    { file: 'ACC-2026-00148.mxf', hash: '9f2c…e41a' },
    { file: 'ACC-2026-00147.mov', hash: '3b07…c9d2' },
    { file: 'ACC-2026-00146.mp4', hash: 'd81e…07fb' },
    { file: 'ACC-2026-00145.mxf', hash: '6a4d…b230' },
];

export default function WelcomeArchival() {
    return (
        <WelcomeFold id="preservation" className="bg-ink text-white">
            <div
                aria-hidden
                className="pointer-events-none absolute -bottom-32 -left-32 size-[28rem] animate-aurora rounded-full bg-brand/30 blur-3xl"
            />
            <div className="relative grid items-center gap-12 lg:grid-cols-[1.2fr_1fr] lg:gap-16">
                <div>
                    <Reveal className="max-w-2xl space-y-3">
                        <p className="text-sm font-medium text-brand-glow">
                            Preservation
                        </p>
                        <h2 className="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                            Built for long-term preservation
                        </h2>
                        <p className="text-lg text-white/70">
                            Records follow archival practice, so they stay
                            trustworthy long after the event.
                        </p>
                    </Reveal>

                    <ul className="mt-10 grid gap-x-8 gap-y-7 sm:grid-cols-2">
                        {FEATURES.map((feature, index) => (
                            <li key={feature.title}>
                                <Reveal
                                    delay={index * 110}
                                    className="group flex gap-4"
                                >
                                    <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-white/10 text-brand-glow ring-1 ring-white/10 transition-colors group-hover:bg-brand-glow group-hover:text-ink">
                                        <feature.icon className="size-5" />
                                    </span>
                                    <div>
                                        <h3 className="font-medium">
                                            {feature.title}
                                        </h3>
                                        <p className="mt-1 text-sm text-white/65">
                                            {feature.text}
                                        </p>
                                    </div>
                                </Reveal>
                            </li>
                        ))}
                    </ul>
                </div>

                <Reveal delay={200}>
                    <div
                        aria-hidden
                        className="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur"
                    >
                        <div className="flex items-center justify-between text-sm">
                            <span className="font-medium">
                                Scheduled fixity check
                            </span>
                            <span className="flex items-center gap-1.5 text-xs text-emerald-300">
                                <span className="size-1.5 animate-pulse rounded-full bg-emerald-400" />
                                Running
                            </span>
                        </div>
                        <ul className="mt-4 divide-y divide-white/10 text-sm">
                            {FIXITY_LOG.map((entry, index) => (
                                <li
                                    key={entry.file}
                                    className="flex items-center gap-3 py-3"
                                >
                                    <span className="min-w-0 flex-1 truncate font-mono text-xs text-white/85">
                                        {entry.file}
                                    </span>
                                    <span className="font-mono text-xs text-white/45">
                                        {entry.hash}
                                    </span>
                                    {index === 0 ? (
                                        <Loader2 className="size-4 animate-spin text-brand-glow" />
                                    ) : (
                                        <span className="flex size-4 items-center justify-center rounded-full bg-emerald-500 text-ink">
                                            <Check className="size-3" />
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                        <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10">
                            <div className="h-full animate-progress rounded-full bg-brand-glow" />
                        </div>
                    </div>
                </Reveal>
            </div>
        </WelcomeFold>
    );
}
