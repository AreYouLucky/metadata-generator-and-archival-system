import AskMeMascot from '../ask-me-mascot';

const ON_RECORD = [
    'What is this video about?',
    'Who speaks, and what are their main points?',
    'What is shown or written on screen?',
    'Which other videos cover the same topic?',
];

const ANYWHERE = [
    'What are the newest videos?',
    'Which videos talk about the budget?',
    'What events are in the archive?',
    'Find videos where someone mentions schools.',
];

type Props = {
    /** On a record's page, suggest questions about it. */
    onRecordPage: boolean;
    disabled: boolean;
    onPick: (question: string) => void;
};

/** The empty chat: what it can do, with a few questions to start from. */
export default function ChatSuggestions({
    onRecordPage,
    disabled,
    onPick,
}: Props) {
    return (
        <div className="grid gap-5 text-sm">
            <div className="flex items-center gap-3 rounded-xl bg-muted/50 p-3 text-muted-foreground">
                <AskMeMascot className="size-16" />
                <p className="leading-relaxed">
                    Ask about the archive’s approved videos: what was said, what
                    is shown, who speaks, or their catalogue details. Answers
                    use only what your account may see, and every time they
                    mention is a link to that moment.
                </p>
            </div>
            <p className="-mb-3 px-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                Try asking
            </p>
            <ul className="grid gap-2">
                {(onRecordPage ? ON_RECORD : ANYWHERE).map((suggestion) => (
                    <li key={suggestion}>
                        <button
                            type="button"
                            disabled={disabled}
                            onClick={() => onPick(suggestion)}
                            className="w-full rounded-xl border border-border bg-background px-3.5 py-2.5 text-left text-sm text-foreground/85 transition-colors hover:border-brand/50 hover:bg-brand/5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50"
                        >
                            {suggestion}
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
}
