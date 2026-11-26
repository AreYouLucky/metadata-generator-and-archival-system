import { Link } from "@inertiajs/react";
import {
    ArrowRight,
    BadgeCheck,
    Fingerprint,
    Languages,
    ScanEye,
} from "lucide-react";
import ScribbleUnderline from "@/components/all/scribble-underline";
import { Button } from "@/components/ui/button";
import { home as catalogue } from "@/routes/catalogue";
import RecordPreview from "./record-preview";
import WelcomeFold from "./welcome-fold";
import WelcomeMarquee from "./welcome-marquee";

const HIGHLIGHTS = [
    { icon: Languages, value: "EN · FIL", label: "Transcripts with speakers" },
    { icon: ScanEye, value: "Every frame", label: "Scenes, text and objects" },
    {
        icon: Fingerprint,
        value: "SHA-256",
        label: "Fixity, checked on schedule",
    },
    { icon: BadgeCheck, value: "100%", label: "Approved by a person" },
];

export default function WelcomeHero() {
    return (
        <WelcomeFold className="bg-linear-to-b from-white via-white to-yellow-50 fold:p-8 fold-short:p-6">
            <div className="grid w-full flex-1 grid-cols-1 items-center gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,40rem)] lg:gap-35">
                <div className="space-y-6 lg:max-w-3xl lg:justify-self-center fold-short:space-y-5">
                    <div className="space-y-5">
                        <h1 className="max-w-3xl animate-in text-4xl leading-[1.05] font-semibold tracking-tight text-balance delay-100 duration-700 fill-mode-both fade-in slide-in-from-bottom-4 sm:text-5xl xl:text-6xl fold-short:text-5xl">
                            Every moment is meant to be{" "}
                            <span className="relative inline-block whitespace-nowrap text-brand">
                                remembered.
                                <ScribbleUnderline />
                            </span>
                        </h1>

                        <p className="max-w-2xl animate-in text-base leading-relaxed sm:text-lg text-pretty text-muted-foreground delay-200 duration-700 fill-mode-both fade-in slide-in-from-bottom-4 fold-short:text-base">
                            Meetings and speeches, event programs and
                            celebrations, the moments your team and your family
                            never want to lose. Each video is transcribed, read
                            frame by frame and carefully described, then checked
                            by a person before it is shared, so you can find it
                            again years from now.
                        </p>
                    </div>

                    <div className="flex animate-in flex-wrap gap-3 delay-300 duration-700 fill-mode-both fade-in slide-in-from-bottom-4 ">
                        <Button
                            asChild
                            size="lg"
                            className="group h-12 w-full rounded-full bg-sky-600 px-6 shadow-none sm:w-auto"
                        >
                            <Link href={catalogue()}>
                                Explore the catalogue
                                <ArrowRight className="transition-transform group-hover:translate-x-1" />
                            </Link>
                        </Button>
                    </div>

                    <dl className="grid max-w-3xl animate-in grid-cols-2 gap-x-6 gap-y-4 border-t pt-6 delay-500 duration-700 fill-mode-both fade-in sm:grid-cols-4 fold-short:pt-4">
                        {HIGHLIGHTS.map((highlight) => (
                            <div key={highlight.label}>
                                <span
                                    aria-hidden
                                    className="mb-2 flex size-8 items-center justify-center rounded-lg bg-sky-50 text-brand fold-short:hidden"
                                >
                                    <highlight.icon className="size-4" />
                                </span>
                                <dt className="sr-only">{highlight.label}</dt>
                                <dd className="text-base font-semibold tracking-tight">
                                    {highlight.value}
                                </dd>
                                <dd className="mt-0.5 text-xs text-muted-foreground">
                                    {highlight.label}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </div>

                <div className="order-first animate-in delay-100 duration-1000 fill-mode-both fade-in slide-in-from-bottom-4 lg:order-none lg:pr-20 lg:delay-300">
                    <RecordPreview />
                </div>
            </div>

            <div className="mt-10 flex w-full items-center gap-6 fold:hidden">
                <p className="hidden shrink-0 text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase sm:block">
                    Recently archived
                </p>
                <WelcomeMarquee className="min-w-0 flex-1" />
            </div>
        </WelcomeFold>
    );
}
