import { BadgeCheck, FileVideo, Globe, Sparkles } from 'lucide-react';
import Reveal from './reveal';
import StepsBackdrop from './steps-backdrop';
import WelcomeFold from './welcome-fold';

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
        <WelcomeFold id="how-it-works" className="border bg-muted">
            <StepsBackdrop />

            <Reveal className="relative max-w-2xl space-y-3">
                <p className="text-sm font-medium text-brand">How it works</p>
                <h2 className="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                    From raw footage to a searchable record
                </h2>
                <p className="text-lg text-muted-foreground">
                    Software does the slow work. People make the decisions.
                </p>
            </Reveal>

            <div className="relative mt-10 lg:mt-14">
                <div
                    aria-hidden
                    className="absolute top-12 right-[12%] left-[12%] hidden h-px bg-linear-to-r from-transparent via-brand/50 to-transparent lg:block"
                />
                <ol className="relative grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:gap-6">
                    {STEPS.map((step, index) => (
                        <li key={step.title}>
                            <Reveal delay={index * 120} className="h-full">
                                <div className="group relative h-full overflow-hidden rounded-2xl border bg-card/80 p-6 shadow-sm backdrop-blur-md transition duration-300 hover:-translate-y-1.5 hover:border-brand/40 hover:shadow-xl hover:shadow-brand/10">
                                    <span
                                        aria-hidden
                                        className="absolute -top-3 right-3 text-7xl font-semibold text-foreground/5 transition-colors group-hover:text-brand/10"
                                    >
                                        0{index + 1}
                                    </span>
                                    <span className="relative flex size-12 items-center justify-center rounded-xl bg-brand-muted text-brand transition-colors duration-300 group-hover:bg-brand group-hover:text-brand-foreground">
                                        <step.icon className="size-5" />
                                    </span>
                                    <h3 className="relative mt-5 text-lg font-semibold">
                                        {step.title}
                                    </h3>
                                    <p className="relative mt-2 text-sm leading-relaxed text-muted-foreground">
                                        {step.text}
                                    </p>
                                </div>
                            </Reveal>
                        </li>
                    ))}
                </ol>
            </div>
        </WelcomeFold>
    );
}
