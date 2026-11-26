import { Link, usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import { useRef, useState } from 'react';
import type { MouseEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import { dashboard, login, register } from '@/routes';
import { home as catalogue } from '@/routes/catalogue';
import { useWelcomeReelStore } from '@/stores/welcome-reel-store';
import { FRAME_COUNT, WELCOME_SECTIONS, frameLabel } from './welcome-sections';

type Props = {
    canRegister: boolean;
};

/**
 * The welcome header's menu below `lg`, where the section links don't fit:
 * a dark sheet listing every frame of the reel plus the account links.
 *
 * A section link closes the sheet first and scrolls once it has closed, so
 * the scroll lock and focus return can't cancel or undo the jump.
 */
export default function WelcomeMobileMenu({ canRegister }: Props) {
    const { auth } = usePage().props;
    const activeFrame = useWelcomeReelStore((state) => state.activeFrame);
    const [isOpen, setIsOpen] = useState(false);
    const pendingSection = useRef<string | null>(null);

    const goToSection = (event: MouseEvent<HTMLAnchorElement>, id: string) => {
        event.preventDefault();
        pendingSection.current = id;
        setIsOpen(false);
    };

    const scrollToPendingSection = (event: Event) => {
        const id = pendingSection.current;

        if (!id) {
            return;
        }

        event.preventDefault();
        pendingSection.current = null;
        window.history.pushState(null, '', `#${id}`);
        document.getElementById(id)?.scrollIntoView({
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)')
                .matches
                ? 'auto'
                : 'smooth',
        });
    };

    return (
        <Sheet open={isOpen} onOpenChange={setIsOpen}>
            <SheetTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Open menu"
                    className="text-foreground/80 hover:bg-foreground/5 hover:text-foreground lg:hidden"
                >
                    <Menu className="size-5" />
                </Button>
            </SheetTrigger>

            <SheetContent
                onCloseAutoFocus={scrollToPendingSection}
                className="w-[min(20rem,85vw)] gap-0 border-l-white/10 bg-foreground text-white"
            >
                <div className="border-b border-white/10 px-6 pt-6 pb-5">
                    <SheetTitle className="font-mono text-[11px] tracking-[0.2em] text-white/50 uppercase">
                        Sections
                    </SheetTitle>
                    <SheetDescription className="mt-1 font-mono text-[11px] tracking-[0.2em] text-white/35">
                        {FRAME_COUNT} FRAMES
                    </SheetDescription>
                </div>

                <nav aria-label="Sections" className="flex-1 overflow-y-auto">
                    <ul className="space-y-1 p-3">
                        {WELCOME_SECTIONS.map((section, index) => {
                            const frame = index + 1;
                            const isActive = frame === activeFrame;

                            return (
                                <li key={section.id}>
                                    <a
                                        href={`#${section.id}`}
                                        aria-current={
                                            isActive ? 'true' : undefined
                                        }
                                        onClick={(event) =>
                                            goToSection(event, section.id)
                                        }
                                        className={cn(
                                            'flex items-center gap-4 rounded-lg px-3 py-3 text-base transition-colors',
                                            isActive
                                                ? 'bg-white/10 text-white'
                                                : 'text-white/70 hover:bg-white/5 hover:text-white',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'font-mono text-xs tabular-nums',
                                                isActive
                                                    ? 'text-tertiary'
                                                    : 'text-white/35',
                                            )}
                                        >
                                            {frameLabel(frame)}
                                        </span>
                                        {section.label}
                                    </a>
                                </li>
                            );
                        })}
                    </ul>
                </nav>

                <div className="grid gap-2 border-t border-white/10 p-4">
                    <Button
                        asChild
                        variant="ghost"
                        className="justify-start text-white/80 hover:bg-white/10 hover:text-white"
                    >
                        <Link href={catalogue()}>Catalogue</Link>
                    </Button>
                    {auth.user ? (
                        <Button
                            asChild
                            className="bg-tertiary text-tertiary-foreground shadow-none hover:bg-tertiary/90"
                        >
                            <Link href={dashboard()}>Dashboard</Link>
                        </Button>
                    ) : (
                        <>
                            <Button
                                asChild
                                variant="outline"
                                className="border-white/20 bg-transparent text-white hover:bg-white/10 hover:text-white"
                            >
                                <Link href={login()}>Log in</Link>
                            </Button>
                            {canRegister && (
                                <Button
                                    asChild
                                    className="bg-tertiary text-tertiary-foreground shadow-none hover:bg-tertiary/90"
                                >
                                    <Link href={register()}>
                                        Create account
                                    </Link>
                                </Button>
                            )}
                        </>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}
