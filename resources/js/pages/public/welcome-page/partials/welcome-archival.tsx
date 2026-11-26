import {
    Check,
    FileLock2,
    Fingerprint,
    History,
    Layers,
    Loader2,
    Scale,
    Timer,
} from 'lucide-react';
import FoldHeading from './fold-heading';
import Reveal from './reveal';
import WelcomeFold from './welcome-fold';

const FEATURES = [
    {
        icon: Fingerprint,
        title: 'Fixity you can prove',
        text: 'MD5 and SHA-256 recorded at upload and re-verified on a schedule.',
    },
    {
        icon: Layers,
        title: 'Versions kept together',
        text: 'Master, mezzanine and proxy copies, multi-reel works and sidecar files.',
    },
    {
        icon: Scale,
        title: 'Rights and embargoes',
        text: 'Copyright, licence, release forms and embargo dates travel with each record.',
    },
    {
        icon: History,
        title: 'Append-only audit trail',
        text: 'Where every file came from, and everything done to it, kept for good.',
    },
    {
        icon: FileLock2,
        title: 'Classification and retention',
        text: 'Restricted material never reaches the public, and disposal dates are tracked.',
    },
    {
        icon: Timer,
        title: 'Frame-accurate segments',
        text: 'Shots and scenes logged with SMPTE timecode in and out.',
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
            <div className="relative grid grid-cols-1 items-center gap-12 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] lg:gap-16 xl:gap-24">
                <div>
                    <FoldHeading
                        eyebrow="Preservation"
                        title="Kept safe today. Still there for the next generation."
                        description="Every record follows archival practice, with the evidence to prove it has not changed."
                        tone="dark"
                    />

                    <ul className="mt-8 grid grid-cols-1 gap-x-10 gap-y-6 sm:mt-10 sm:grid-cols-2 sm:gap-y-7 fold-short:mt-6 fold-short:gap-y-5">
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
                        className="rounded-2xl border border-white/10 bg-white/5 p-5 sm:p-6"
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
