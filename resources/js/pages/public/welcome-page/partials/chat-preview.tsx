import { Scissors, SendHorizontal } from 'lucide-react';
import AskMeMascot from '@/components/all/ask-me-mascot';
import Reveal from './reveal';

const CITATIONS = ['18:05', '22:40'];

/**
 * Illustration of the "Ask me" assistant answering from a record, with the
 * clip and YouTube upload that follow. Purely decorative sample content.
 */
export default function ChatPreview() {
    return (
        <div aria-hidden className="relative mx-auto w-full max-w-lg">
            <div className="relative overflow-hidden rounded-2xl border bg-card shadow-2xl shadow-sky-900/10">
                <div className="flex items-center justify-between border-b px-4 py-3">
                    <span className="flex items-center gap-2 text-sm font-medium">
                        <AskMeMascot className="-my-4 size-16" />
                        Ask me
                    </span>
                    <span className="truncate pl-3 text-[11px] text-muted-foreground">
                        Lola Remy’s 80th Birthday
                    </span>
                </div>

                <div className="space-y-3 p-4 text-sm">
                    <Reveal delay={150} className="flex justify-end">
                        <p className="max-w-[80%] rounded-2xl rounded-br-md bg-primary px-3.5 py-2 text-primary-foreground">
                            When do the grandchildren give their wishes?
                        </p>
                    </Reveal>

                    <Reveal delay={450} className="flex">
                        <div className="max-w-[88%] space-y-2 rounded-2xl rounded-bl-md bg-muted px-3.5 py-2.5">
                            <p className="leading-relaxed">
                                Right after the opening prayer. Their wishes
                                start at 18:05, and Mia’s message to Lola comes
                                at 22:40.
                            </p>
                            <div className="flex flex-wrap gap-1.5">
                                {CITATIONS.map((time) => (
                                    <span
                                        key={time}
                                        className="rounded-md bg-background px-1.5 py-0.5 font-mono text-[11px] font-medium text-brand ring-1 ring-sky-200"
                                    >
                                        ▶ {time}
                                    </span>
                                ))}
                            </div>
                        </div>
                    </Reveal>

                    <div className="flex items-center gap-2 rounded-xl border bg-background px-3 py-2 text-muted-foreground">
                        <span className="flex-1 truncate text-xs">
                            Ask about this recording…
                        </span>
                        <SendHorizontal className="size-4 text-brand" />
                    </div>
                </div>

                <Reveal
                    delay={750}
                    className="flex items-center gap-3 border-t bg-muted/40 px-4 py-3 text-xs"
                >
                    <span className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-tertiary text-tertiary-foreground">
                        <Scissors className="size-3.5" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="truncate font-medium">
                            Clip 18:05 – 22:55 · Publishing to YouTube
                        </p>
                        <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-muted">
                            <div className="h-full animate-progress rounded-full bg-brand" />
                        </div>
                    </div>
                </Reveal>
            </div>
        </div>
    );
}
