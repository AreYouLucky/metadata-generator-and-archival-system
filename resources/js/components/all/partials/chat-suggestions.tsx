import { MessageSquareText } from 'lucide-react';

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
        <div className="grid gap-3 p-2 text-sm">
            <div className="flex items-start gap-2 text-muted-foreground">
                <MessageSquareText
                    className="mt-0.5 size-4 shrink-0"
                    aria-hidden
                />
                <p>
                    Ask about the archive’s approved videos: what was said, what
                    is shown, who speaks, or their catalogue details. Answers
                    use only what your account may see and link to the moments
                    they come from.
                </p>
            </div>
            <ul className="grid gap-1.5">
                {(onRecordPage ? ON_RECORD : ANYWHERE).map((suggestion) => (
                    <li key={suggestion}>
                        <button
                            type="button"
                            disabled={disabled}
                            onClick={() => onPick(suggestion)}
                            className="w-full rounded-md border border-border px-3 py-1.5 text-left text-xs text-foreground/80 transition-colors hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50"
                        >
                            {suggestion}
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
}
