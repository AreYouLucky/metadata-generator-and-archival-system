import { Link } from '@inertiajs/react';
import { LogIn, MessageCircleQuestion } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useChatUser } from '@/hooks/use-chat';
import { cn } from '@/lib/utils';
import { login } from '@/routes';
import { useAskMeStore } from '@/stores/ask-me-store';
import ChatPanel from './partials/chat-panel';

/**
 * "Ask me": a floating button at the bottom right of every page that opens
 * the archive assistant in a floating window (A in the workspace, Esc
 * closes). It answers only from approved records this account may see.
 * Guests are asked to log in. The window stays mounted once opened, so a
 * half-typed question survives closing it; the conversation loads the first
 * time it opens.
 */
export default function AskMe() {
    const user = useChatUser();
    const open = useAskMeStore((state) => state.open);
    const setOpen = useAskMeStore((state) => state.setOpen);
    const [mounted, setMounted] = useState(open);

    useEffect(() => {
        if (open) {
            setMounted(true);
        }
    }, [open]);

    return (
        <div className="pointer-events-none fixed right-4 bottom-4 z-50 flex flex-col items-end gap-3 text-foreground">
            {mounted && (
                <div
                    role="dialog"
                    aria-label="Ask me about the archive"
                    hidden={!open}
                    onKeyDown={(event) => {
                        if (event.key === 'Escape') {
                            event.stopPropagation();
                            setOpen(false);
                        }
                    }}
                    className={cn(
                        'pointer-events-auto w-[min(24rem,calc(100vw-2rem))] overflow-hidden rounded-xl border border-border bg-card shadow-2xl shadow-sky-900/15',
                        user ? 'h-[min(36rem,calc(100dvh-7rem))]' : 'h-auto',
                    )}
                >
                    {user ? (
                        <ChatPanel
                            userId={user.id}
                            active={open}
                            onClose={() => setOpen(false)}
                        />
                    ) : (
                        <div className="flex flex-col items-center justify-center gap-4 p-6 text-center text-sm text-foreground/80">
                            <MessageCircleQuestion
                                className="size-8 text-brand"
                                aria-hidden
                            />
                            <p>
                                Log in to ask about the archive’s videos: what
                                was said, what is shown, who speaks.
                            </p>
                            <Link
                                href={login()}
                                className="inline-flex items-center gap-2 rounded-md bg-brand px-4 py-2 font-semibold text-brand-foreground hover:brightness-110"
                            >
                                <LogIn className="size-4" aria-hidden />
                                Log in
                            </Link>
                        </div>
                    )}
                </div>
            )}
            <button
                type="button"
                onClick={() => setOpen(!open)}
                aria-expanded={open}
                title="Ask me about the archive"
                className="pointer-events-auto flex items-center gap-2 rounded-full bg-brand px-4 py-2.5 text-sm font-semibold text-brand-foreground shadow-lg shadow-sky-900/15 transition hover:brightness-110 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
            >
                <MessageCircleQuestion className="size-4" aria-hidden />
                {open ? 'Close' : 'Ask me'}
            </button>
        </div>
    );
}
