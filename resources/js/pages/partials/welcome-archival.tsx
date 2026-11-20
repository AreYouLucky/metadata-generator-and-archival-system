import { FileLock2, Fingerprint, History, Scale } from 'lucide-react';

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

export default function WelcomeArchival() {
    return (
        <section className="bg-ink text-white">
            <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                <div className="max-w-2xl space-y-3">
                    <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        Built for long-term preservation
                    </h2>
                    <p className="text-white/70">
                        Records follow archival practice, so they stay
                        trustworthy long after the event.
                    </p>
                </div>

                <ul className="mt-10 grid gap-x-8 gap-y-8 sm:grid-cols-2">
                    {FEATURES.map((feature) => (
                        <li key={feature.title} className="flex gap-4">
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white/10 text-brand">
                                <feature.icon className="size-5" />
                            </span>
                            <div>
                                <h3 className="font-medium">{feature.title}</h3>
                                <p className="mt-1 text-sm text-white/70">
                                    {feature.text}
                                </p>
                            </div>
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}
