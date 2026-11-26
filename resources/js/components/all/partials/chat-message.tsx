import { AlertTriangle, Check, Copy, Info, RotateCcw } from 'lucide-react';
import { useClipboard } from '@/hooks/use-clipboard';
import { cn } from '@/lib/utils';
import type { ChatMessage as Message } from '@/types/chat';
import ChatAnswerText from './chat-answer-text';
import ChatCitation from './chat-citation';

type Props = {
    message: Message;
    /** Ask the question behind a failed answer again. */
    onRetry?: () => void;
    retryDisabled?: boolean;
};

/** Where an answer's facts came from, by function tool. */
const SOURCES: Record<string, string> = {
    search_records: 'record search',
    get_record_overview: 'catalogue',
    search_transcript: 'transcript',
    read_transcript: 'transcript',
    get_generated_metadata: 'AI metadata',
    get_visual_content: 'key frames',
    get_speakers: 'speakers',
    get_archival_details: 'archive record',
    get_technical_details: 'technical data',
};

/**
 * One question or answer. Answers show the moments they rely on (click a
 * time in the text or a chip to play it) and which of the record's data
 * they came from, and can be copied; an answer the data doesn't support
 * says so, and a failure explains itself and can be retried.
 */
export default function ChatMessage({
    message,
    onRetry,
    retryDisabled,
}: Props) {
    const [copied, copy] = useClipboard();

    if (message.role === 'user') {
        return (
            <li className="ml-10 max-w-[85%] self-end rounded-2xl rounded-br-md bg-brand px-4 py-3 text-[15px] leading-[1.7] whitespace-pre-wrap text-brand-foreground shadow-sm">
                {message.content}
            </li>
        );
    }

    const sources = [
        ...new Set(message.tools.map((tool) => SOURCES[tool]).filter(Boolean)),
    ];
    const failed = message.status === 'failed';

    return (
        <li
            className={cn(
                'mr-2 grid gap-4 rounded-2xl rounded-bl-md border px-5 py-4 text-[15px] shadow-sm',
                failed
                    ? 'border-destructive/30 bg-destructive/10'
                    : 'border-border bg-background',
            )}
        >
            {message.status !== 'answered' && (
                <p className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                    {failed ? (
                        <AlertTriangle className="size-3.5" aria-hidden />
                    ) : (
                        <Info className="size-3.5" aria-hidden />
                    )}
                    {failed
                        ? 'Could not answer'
                        : 'Not in the archive you can see'}
                </p>
            )}
            {failed ? (
                <p className="leading-[1.75] whitespace-pre-wrap">
                    {message.content}
                </p>
            ) : (
                <ChatAnswerText
                    content={message.content}
                    citations={message.citations}
                    link={message.link}
                    recordId={message.record_id}
                />
            )}
            {message.citations.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {message.citations.map((citation) => (
                        <ChatCitation
                            key={`${citation.record_id}-${citation.start}-${citation.end}`}
                            citation={citation}
                        />
                    ))}
                </div>
            )}
            <div className="-mb-1.5 flex items-center gap-2 border-t border-border/60 pt-2.5 text-[11px] text-muted-foreground">
                <span className="min-w-0 flex-1 truncate">
                    {message.status === 'answered' && sources.length > 0
                        ? `From: ${sources.join(', ')}`
                        : null}
                </span>
                {failed && onRetry ? (
                    <button
                        type="button"
                        onClick={onRetry}
                        disabled={retryDisabled}
                        className="inline-flex items-center gap-1 rounded-md px-1.5 py-1 font-medium text-foreground/80 transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50"
                    >
                        <RotateCcw className="size-3" aria-hidden />
                        Try again
                    </button>
                ) : (
                    !failed && (
                        <button
                            type="button"
                            onClick={() => void copy(message.content)}
                            className="inline-flex items-center gap-1 rounded-md px-1.5 py-1 transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            aria-label="Copy the answer"
                        >
                            {copied === message.content ? (
                                <Check className="size-3" aria-hidden />
                            ) : (
                                <Copy className="size-3" aria-hidden />
                            )}
                            {copied === message.content ? 'Copied' : 'Copy'}
                        </button>
                    )
                )}
            </div>
        </li>
    );
}
