import {
    Check,
    Circle,
    FileText,
    ListTree,
    ScrollText,
    Tags,
} from 'lucide-react';
import GeneratedBackdrop from './generated-backdrop';
import Reveal from './reveal';
import WelcomeFold from './welcome-fold';

const TRANSCRIPT = [
    {
        time: '00:12:41',
        speaker: 'S1',
        text: 'Good morning, and welcome to this year’s Regional Science and Technology Week.',
    },
    {
        time: '00:12:47',
        speaker: 'S2',
        text: 'Magandang umaga po sa ating mga bisita mula sa iba’t ibang probinsya.',
    },
    {
        time: '00:13:02',
        speaker: 'S1',
        text: 'Today we open the exhibit halls to students from forty schools in the region.',
    },
];

const CHAPTERS = [
    { time: '00:00', title: 'Arrival of guests' },
    { time: '04:32', title: 'Opening remarks' },
    { time: '18:05', title: 'Keynote: research in the regions' },
    { time: '41:50', title: 'Awarding of partners' },
];

const TAGS = [
    'science week',
    'opening ceremony',
    'innovation',
    'students',
    'Cebu City',
    'Region VII',
    'keynote',
    'awards',
];

const REVIEW = [
    { label: 'Title and summary', done: true },
    { label: 'Rights and consent', done: true },
    { label: 'Security classification', done: false },
];

/**
 * The metadata drafted for every video, shown as a bento of sample cards.
 */
export default function WelcomeGenerated() {
    return (
        <WelcomeFold id="metadata" className="border bg-card">
            <GeneratedBackdrop />

            <Reveal className="relative flex flex-wrap items-end justify-between gap-4">
                <div className="max-w-2xl space-y-3">
                    <p className="text-sm font-medium text-brand">
                        AI-assisted metadata
                    </p>
                    <h2 className="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                        What gets drafted for every video
                    </h2>
                </div>
                <p className="max-w-sm text-muted-foreground">
                    Drafts are a starting point. Nothing is published until an
                    archivist has checked it.
                </p>
            </Reveal>

            <div
                aria-hidden
                className="relative mt-8 grid gap-4 lg:mt-10 lg:grid-cols-3"
            >
                <Reveal className="lg:col-span-2">
                    <div className="h-full rounded-2xl border bg-background/75 p-5 shadow-sm backdrop-blur-md">
                        <div className="flex items-center justify-between">
                            <span className="flex items-center gap-2 text-sm font-medium">
                                <ScrollText className="size-4 text-brand" />
                                Transcript
                            </span>
                            <span className="flex gap-1.5 text-[11px] font-medium">
                                <span className="rounded-md bg-brand-muted px-2 py-0.5 text-brand">
                                    EN
                                </span>
                                <span className="rounded-md bg-amber-100 px-2 py-0.5 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                    FIL
                                </span>
                            </span>
                        </div>
                        <ul className="mt-4 space-y-2.5 text-sm">
                            {TRANSCRIPT.map((line, index) => (
                                <li key={line.time}>
                                    <Reveal
                                        delay={250 + index * 220}
                                        className="flex gap-3"
                                    >
                                        <span className="shrink-0 font-mono text-xs leading-5 text-brand">
                                            {line.time}
                                        </span>
                                        <span className="shrink-0 text-xs leading-5 font-medium text-muted-foreground">
                                            {line.speaker}
                                        </span>
                                        <span className="text-foreground/90">
                                            {line.text}
                                            {index ===
                                                TRANSCRIPT.length - 1 && (
                                                <span className="ml-1 inline-block h-4 w-1.5 translate-y-0.5 animate-pulse rounded-sm bg-brand" />
                                            )}
                                        </span>
                                    </Reveal>
                                </li>
                            ))}
                        </ul>
                    </div>
                </Reveal>

                <Reveal delay={120}>
                    <div className="flex h-full flex-col rounded-2xl border bg-background/75 p-5 shadow-sm backdrop-blur-md">
                        <span className="flex items-center gap-2 text-sm font-medium">
                            <FileText className="size-4 text-brand" />
                            Summary
                        </span>
                        <p className="mt-4 text-sm leading-relaxed text-muted-foreground">
                            The regional office opens Science and Technology
                            Week in Cebu City with remarks, a keynote on
                            research in the regions and awards for partner
                            institutions.
                        </p>
                        <span className="mt-auto inline-flex w-fit items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                            <span className="size-1.5 animate-pulse rounded-full bg-current" />
                            Draft · awaiting review
                        </span>
                    </div>
                </Reveal>

                <Reveal delay={200}>
                    <div className="h-full rounded-2xl border bg-background/75 p-5 shadow-sm backdrop-blur-md">
                        <span className="flex items-center gap-2 text-sm font-medium">
                            <ListTree className="size-4 text-brand" />
                            Chapters
                        </span>
                        <ol className="mt-4 space-y-2 border-l pl-4 text-sm">
                            {CHAPTERS.map((chapter) => (
                                <li
                                    key={chapter.time}
                                    className="relative flex gap-3"
                                >
                                    <span className="absolute top-1.5 -left-[1.31rem] size-2 rounded-full bg-brand ring-4 ring-background" />
                                    <span className="font-mono text-xs leading-5 text-brand">
                                        {chapter.time}
                                    </span>
                                    <span className="truncate">
                                        {chapter.title}
                                    </span>
                                </li>
                            ))}
                        </ol>
                    </div>
                </Reveal>

                <Reveal delay={280}>
                    <div className="h-full rounded-2xl border bg-background/75 p-5 shadow-sm backdrop-blur-md">
                        <span className="flex items-center gap-2 text-sm font-medium">
                            <Tags className="size-4 text-brand" />
                            Tags
                        </span>
                        <div className="mt-4 flex flex-wrap gap-1.5">
                            {TAGS.map((tag) => (
                                <span
                                    key={tag}
                                    className="rounded-lg border px-2.5 py-1 text-xs text-muted-foreground transition-colors hover:border-brand/50 hover:bg-brand-muted hover:text-brand"
                                >
                                    #{tag}
                                </span>
                            ))}
                        </div>
                    </div>
                </Reveal>

                <Reveal delay={360}>
                    <div className="h-full rounded-2xl border bg-background/75 p-5 shadow-sm backdrop-blur-md">
                        <span className="flex items-center gap-2 text-sm font-medium">
                            <Check className="size-4 text-brand" />
                            Admin review
                        </span>
                        <ul className="mt-4 space-y-2.5 text-sm">
                            {REVIEW.map((item) => (
                                <li
                                    key={item.label}
                                    className="flex items-center gap-2.5"
                                >
                                    {item.done ? (
                                        <span className="flex size-5 items-center justify-center rounded-full bg-emerald-600 text-white">
                                            <Check className="size-3" />
                                        </span>
                                    ) : (
                                        <Circle className="size-5 text-muted-foreground/50" />
                                    )}
                                    <span
                                        className={
                                            item.done
                                                ? ''
                                                : 'text-muted-foreground'
                                        }
                                    >
                                        {item.label}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                </Reveal>
            </div>
        </WelcomeFold>
    );
}
