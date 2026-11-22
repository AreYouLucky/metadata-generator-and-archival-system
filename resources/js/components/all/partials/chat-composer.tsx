import { SendHorizontal } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent, KeyboardEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

const MAX_LENGTH = 1000;

type Props = {
    busy: boolean;
    /** The window is showing: put the cursor in the box. */
    active: boolean;
    onAsk: (question: string) => Promise<unknown>;
};

/**
 * The question box: Enter sends, Shift+Enter starts a new line. Disabled
 * while the previous question is being answered.
 */
export default function ChatComposer({ busy, active, onAsk }: Props) {
    const [question, setQuestion] = useState('');
    const trimmed = question.trim();
    const box = useRef<HTMLTextAreaElement>(null);

    useEffect(() => {
        if (active) {
            box.current?.focus();
        }
    }, [active]);

    const submit = (event?: FormEvent) => {
        event?.preventDefault();

        if (busy || trimmed.length < 2) {
            return;
        }

        onAsk(trimmed).then(
            () => setQuestion(''),
            () => undefined,
        );
    };

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (
            event.key === 'Enter' &&
            !event.shiftKey &&
            !event.nativeEvent.isComposing
        ) {
            event.preventDefault();
            submit();
        }
    };

    return (
        <form
            onSubmit={submit}
            className="grid gap-1.5 border-t border-border p-3"
        >
            <div className="flex items-end gap-2">
                <Textarea
                    ref={box}
                    value={question}
                    onChange={(event) => setQuestion(event.target.value)}
                    onKeyDown={onKeyDown}
                    maxLength={MAX_LENGTH}
                    rows={1}
                    placeholder="Ask about the archive…"
                    aria-label="Question about the archive"
                    className="max-h-32 min-h-9 resize-none text-sm"
                />
                <Button
                    type="submit"
                    size="icon"
                    disabled={busy || trimmed.length < 2}
                    aria-label="Ask"
                >
                    {busy ? <Spinner /> : <SendHorizontal />}
                </Button>
            </div>
            <p className="text-[11px] text-muted-foreground">
                Answers come only from approved videos your account may see.
                Your question and the parts of those videos it needs are sent to
                OpenAI.
            </p>
        </form>
    );
}
