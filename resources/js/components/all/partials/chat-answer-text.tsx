import { Fragment, useMemo } from 'react';
import type { ReactNode } from 'react';
import { splitTimestamps } from '@/lib/utilities';
import type { ChatCitation, ChatLink } from '@/types/chat';
import ChatTimeLink from './chat-time-link';

type Props = {
    content: string;
    citations: ChatCitation[];
    /** Where a time off the citations goes. */
    link: ChatLink | null;
    /** The record whose page the question was asked on, if any. */
    recordId: string | null;
};

type Block =
    | { kind: 'paragraph'; text: string }
    | { kind: 'list'; ordered: boolean; items: string[] };

const BULLET = /^\s*(?:[-•*]|\d{1,2}[.)])\s+/;
const NUMBERED = /^\s*\d{1,2}[.)]\s+/;

/** Paragraphs (split at blank lines) and "- " / "1. " lists, in order. */
function toBlocks(content: string): Block[] {
    const blocks: Block[] = [];
    let paragraph: string[] = [];

    const flush = () => {
        if (paragraph.length > 0) {
            blocks.push({ kind: 'paragraph', text: paragraph.join('\n') });
            paragraph = [];
        }
    };

    for (const line of content.split('\n')) {
        if (line.trim() === '') {
            flush();
            continue;
        }

        if (BULLET.test(line)) {
            flush();
            const ordered = NUMBERED.test(line);
            const item = line.replace(BULLET, '');
            const previous = blocks.at(-1);

            if (previous?.kind === 'list' && previous.ordered === ordered) {
                previous.items.push(item);
            } else {
                blocks.push({ kind: 'list', ordered, items: [item] });
            }

            continue;
        }

        paragraph.push(line);
    }

    flush();

    return blocks;
}

/**
 * An answer's text: paragraphs and lists, with every time it mentions
 * ("0:42", "1:02:05") clickable to play that moment.
 */
export default function ChatAnswerText({
    content,
    citations,
    link,
    recordId,
}: Props) {
    const blocks = useMemo(() => toBlocks(content), [content]);

    const inline = (text: string): ReactNode =>
        splitTimestamps(text).map((part, index) =>
            part.seconds === null ? (
                <Fragment key={index}>{part.text}</Fragment>
            ) : (
                <ChatTimeLink
                    key={index}
                    label={part.text}
                    seconds={part.seconds}
                    citations={citations}
                    link={link}
                    recordId={recordId}
                />
            ),
        );

    return (
        <div className="grid gap-4 leading-[1.75] text-pretty">
            {blocks.map((block, index) => {
                if (block.kind === 'paragraph') {
                    return (
                        <p key={index} className="whitespace-pre-line">
                            {inline(block.text)}
                        </p>
                    );
                }

                const List = block.ordered ? 'ol' : 'ul';

                return (
                    <List
                        key={index}
                        className={
                            block.ordered
                                ? 'grid list-decimal gap-2.5 pl-5 marker:text-muted-foreground'
                                : 'grid list-disc gap-2.5 pl-5 marker:text-brand'
                        }
                    >
                        {block.items.map((item, position) => (
                            <li key={position} className="pl-1">
                                {inline(item)}
                            </li>
                        ))}
                    </List>
                );
            })}
        </div>
    );
}
