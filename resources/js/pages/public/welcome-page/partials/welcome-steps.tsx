import { BadgeCheck, CloudUpload, Globe, ScanEye } from "lucide-react";
import FoldHeading from "./fold-heading";
import Reveal from "./reveal";
import WelcomeFold from "./welcome-fold";

const STEPS = [
    {
        icon: CloudUpload,
        title: "Upload",
        text: "Drag and drop, paste a link or send a whole batch. Every master is checksummed the moment it arrives.",
    },
    {
        icon: ScanEye,
        title: "Understand",
        text: "Speech becomes a timestamped transcript with speakers labelled, while shots, key frames, on-screen text and objects are read automatically.",
    },
    {
        icon: BadgeCheck,
        title: "Review",
        text: "Encoders polish the drafts. An admin checks rights and classification, then approves the record or sends it back with notes.",
    },
    {
        icon: Globe,
        title: "Share",
        text: "Approved records go live in the catalogue, ready to publish to YouTube or deliver through the public API.",
    },
];

export default function WelcomeSteps() {
    return (
        <WelcomeFold
            id="how-it-works"
            className="border bg-linear-to-b from-white via-white to-sky-100"
        >
            {/* <StepsBackdrop /> */}

            <FoldHeading
                title="Upload once. We take it from there."
                description="The software does the heavy lifting: checksums, transcripts, scene detection and first drafts. People make the final call on every record."
                layout="split"
            />

            <div className="relative mt-8 sm:mt-10 lg:mt-14 fold-short:mt-8">
                <ol className="relative -mx-6 flex snap-x snap-mandatory scroll-px-6 gap-3 overflow-x-auto overflow-y-hidden px-6 pb-1 [scrollbar-width:none] sm:mx-0 sm:grid sm:grid-cols-2 sm:gap-4 sm:overflow-visible sm:px-0 sm:pb-0 lg:grid-cols-4 lg:gap-6 [&::-webkit-scrollbar]:hidden">
                    {STEPS.map((step, index) => (
                        <li
                            key={step.title}
                            className="w-[80%] max-w-xs shrink-0 snap-start sm:w-auto sm:max-w-none"
                        >
                            <Reveal delay={index * 120} className="h-full">
                                <div className="group relative flex h-full flex-col rounded-2xl border bg-card p-6 transition duration-300 hover:-translate-y-1.5 hover:border-brand/40 hover:shadow-xl hover:shadow-brand/10 sm:p-7 fold-short:p-6">
                                    <div className="flex items-center justify-between">
                                        <span className="flex size-12 items-center justify-center rounded-xl bg-sky-500 text-brand transition-colors duration-300 text-white group-hover:bg-brand group-hover:text-brand-foreground">
                                            <step.icon className="size-5" />
                                        </span>
                                        <span className="font-mono text-xs tracking-[0.2em] text-muted-foreground">
                                            STEP 0{index + 1}
                                        </span>
                                    </div>
                                    <h3 className="mt-6 text-lg font-semibold">
                                        {step.title}
                                    </h3>
                                    <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
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
