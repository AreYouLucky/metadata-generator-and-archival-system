import { AlertTriangle, Info } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { ChatMessage as Message } from '@/types/chat';
import ChatCitation from './chat-citation';

type Props = { message: Message };

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
 * One question or answer. Answers show the moments they rely on (click to
 * play) and which of the record's data they came from; an answer the data
 * doesn't support says so, and a failure explains itself.
 */
export default function ChatMessage({ message }: Props) {
    if (message.role === 'user') {
        return (
            <li className="ml-8 self-end rounded-lg rounded-br-sm bg-brand/20 px-3 py-2 text-sm whitespace-pre-wrap">
                {message.content}
            </li>
        );
    }

    const sources = [
        ...new Set(message.tools.map((tool) => SOURCES[tool]).filter(Boolean)),
    ];

    return (
        <li
            className={cn(
                'mr-6 grid gap-2 rounded-lg rounded-bl-sm px-3 py-2 text-sm',
                message.status === 'failed'
                    ? 'bg-destructive/15'
                    : 'bg-muted/60',
            )}
        >
            {message.status !== 'answered' && (
                <p className="flex items-center gap-1.5 text-[11px] font-medium text-muted-foreground">
                    {message.status === 'failed' ? (
                        <AlertTriangle className="size-3" aria-hidden />
                    ) : (
                        <Info className="size-3" aria-hidden />
                    )}
                    {message.status === 'failed'
                        ? 'Could not answer'
                        : 'Not in the archive you can see'}
                </p>
            )}
            <p className="leading-relaxed whitespace-pre-wrap">
                {message.content}
            </p>
            {message.citations.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                    {message.citations.map((citation) => (
                        <ChatCitation
                            key={`${citation.record_id}-${citation.start}-${citation.end}`}
                            citation={citation}
                        />
                    ))}
                </div>
            )}
            {message.status === 'answered' && sources.length > 0 && (
                <p className="text-[11px] text-muted-foreground">
                    From: {sources.join(', ')}
                </p>
            )}
        </li>
    );
}
