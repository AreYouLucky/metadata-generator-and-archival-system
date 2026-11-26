import { CornerDownRight } from 'lucide-react';

type Props = {
    questions: string[];
    disabled: boolean;
    onPick: (question: string) => void;
};

/** Questions the latest answer suggests asking next, one click to ask. */
export default function ChatFollowUps({ questions, disabled, onPick }: Props) {
    return (
        <div className="mt-6 grid gap-2.5 pl-1">
            <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                Ask next
            </p>
            <ul className="flex flex-wrap gap-2">
                {questions.map((question) => (
                    <li key={question} className="max-w-full">
                        <button
                            type="button"
                            disabled={disabled}
                            onClick={() => onPick(question)}
                            className="inline-flex max-w-full items-start gap-1.5 rounded-xl border border-brand/30 bg-brand/5 px-3.5 py-2 text-left text-[13px] leading-snug text-foreground/85 transition-colors hover:border-brand/60 hover:bg-brand/10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50"
                        >
                            <CornerDownRight
                                className="mt-0.5 size-3 shrink-0 text-brand"
                                aria-hidden
                            />
                            {question}
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
}
