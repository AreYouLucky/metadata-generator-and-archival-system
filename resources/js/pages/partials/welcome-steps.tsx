import { BadgeCheck, FileVideo, Globe, Sparkles } from 'lucide-react';

const STEPS = [
    {
        icon: FileVideo,
        title: 'Upload',
        text: 'Encoders upload the master file. It is checksummed on arrival, so any later change to the file can be detected.',
    },
    {
        icon: Sparkles,
        title: 'Describe',
        text: 'Speech is transcribed in English and Filipino, then titles, a summary, tags and chapters are drafted for review.',
    },
    {
        icon: BadgeCheck,
        title: 'Review',
        text: 'An admin checks the metadata, rights and security classification before approving the record.',
    },
    {
        icon: Globe,
        title: 'Publish',
        text: 'Approved, unclassified records with public rights appear in the catalogue for everyone to browse.',
    },
];

export default function WelcomeSteps() {
    return (
        <section className="border-y bg-muted/40">
            <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                <div className="max-w-2xl space-y-3">
                    <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        From raw footage to a searchable record
                    </h2>
                    <p className="text-muted-foreground">
                        Software does the slow work. People make the decisions.
                    </p>
                </div>

                <ol className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {STEPS.map((step, index) => (
                        <li
                            key={step.title}
                            className="relative rounded-xl border bg-card p-5"
                        >
                            <div className="flex items-center justify-between">
                                <span className="flex size-10 items-center justify-center rounded-lg bg-brand-muted text-brand">
                                    <step.icon className="size-5" />
                                </span>
                                <span className="font-mono text-xs text-muted-foreground">
                                    0{index + 1}
                                </span>
                            </div>
                            <h3 className="mt-4 font-semibold">{step.title}</h3>
                            <p className="mt-1.5 text-sm text-muted-foreground">
                                {step.text}
                            </p>
                        </li>
                    ))}
                </ol>
            </div>
        </section>
    );
}
