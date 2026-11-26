import { Link, usePage } from "@inertiajs/react";
import { ArrowRight } from "lucide-react";
import BrandLogo from "@/components/all/brand-logo";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { dashboard, home, login, register } from "@/routes";
import { home as catalogue } from "@/routes/catalogue";
import { useWelcomeReelStore } from "@/stores/welcome-reel-store";
import WelcomeMobileMenu from "./welcome-mobile-menu";
import { FRAME_COUNT, WELCOME_SECTIONS, frameLabel } from "./welcome-sections";

type Props = {
    canRegister: boolean;
};

const GHOST_CLASS =
    "text-foreground/80 hover:bg-foreground/5 hover:text-foreground";
const ACCENT_CLASS =
    "bg-tertiary text-tertiary-foreground shadow-none hover:bg-tertiary/90";

/**
 * The welcome header, styled as the leader of the film reel it sits on: a
 * white bar above the tape, with each section's frame number, the
 * current frame highlighted and a progress line that follows the reel.
 * Below `lg` the section links move into WelcomeMobileMenu.
 */
export default function WelcomeHeader({ canRegister }: Props) {
    const { auth, name } = usePage().props;
    const activeFrame = useWelcomeReelStore((state) => state.activeFrame);

    return (
        <header className="sticky top-0 z-50 -mx-4 animate-in bg-white px-4 text-foreground duration-700 fill-mode-both fade-in sm:-mx-6 sm:px-6 lg:-mx-10 lg:px-10 fold:-mb-17 border-b border-yellow-200">
            <div className="flex h-14 items-center justify-between gap-3 pt-2 lg:h-17 lg:gap-4 ">
                <div className="flex min-w-0 items-center gap-4">
                    <Link
                        href={home()}
                        className="group flex items-center font-semibold"
                    >
                        <BrandLogo
                            variant="compact"
                            tone="default"
                            alt={name}
                            className="h-8 transition-transform duration-300 group-hover:-translate-y-0.5 sm:h-8"
                        />
                    </Link>
                    {activeFrame >= 0 && (
                        <span
                            aria-hidden
                            className="hidden items-center gap-2 border-l border-foreground/15 pl-4 font-mono text-[11px] tracking-[0.2em] text-foreground/50 xl:flex"
                        >
                            FRAME
                            <span className="text-sky-600 tabular-nums">
                                {frameLabel(activeFrame)}
                            </span>
                            / {frameLabel(FRAME_COUNT - 1)}
                        </span>
                    )}
                </div>

                <nav
                    aria-label="Sections"
                    className="hidden items-center gap-1 text-sm lg:flex"
                >
                    {WELCOME_SECTIONS.map((section, index) => {
                        const frame = index + 1;
                        const isActive = frame === activeFrame;

                        return (
                            <a
                                key={section.id}
                                href={`#${section.id}`}
                                aria-current={isActive ? "true" : undefined}
                                className={cn(
                                    "group flex items-center gap-2 rounded-lg px-3 py-2 transition-colors",
                                    isActive
                                        ? "bg-foreground/5 text-foreground"
                                        : "text-foreground/60 hover:text-foreground",
                                )}
                            >
                                <span
                                    className={cn(
                                        "font-mono text-[10px] tabular-nums transition-colors",
                                        isActive
                                            ? "text-sky-600"
                                            : "text-foreground/35 group-hover:text-foreground/60",
                                    )}
                                >
                                    {frameLabel(frame)}
                                </span>
                                {section.label}
                            </a>
                        );
                    })}
                </nav>

                <div className="flex shrink-0 items-center gap-1 sm:gap-2">
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className={cn(GHOST_CLASS, "hidden sm:inline-flex")}
                    >
                        <Link href={catalogue()}>Catalogue</Link>
                    </Button>
                    {auth.user ? (
                        <Button asChild size="sm" className={ACCENT_CLASS}>
                            <Link href={dashboard()}>Dashboard</Link>
                        </Button>
                    ) : (
                        <>
                            <Button
                                asChild
                                variant="ghost"
                                size="sm"
                                className={GHOST_CLASS}
                            >
                                <Link href={login()}>Log in</Link>
                            </Button>
                            {canRegister && (
                                <Button
                                    asChild
                                    className="group hidden h-9 gap-2.5 rounded-full bg-black py-1 pr-4 pl-4 text-white shadow-sm  transition-all hover:bg-foreground/90 hover:shadow-md sm:inline-flex"
                                >
                                    <Link href={register()}>
                                        Create account
                                    </Link>
                                </Button>
                            )}
                        </>
                    )}
                    <WelcomeMobileMenu canRegister={canRegister} />
                </div>
            </div>

            <div
                aria-hidden
                className="absolute inset-x-0 bottom-0 h-0.5 bg-foreground/10"
            >
                <div
                    className="h-full origin-left bg-tertiary"
                    style={{ transform: "scaleX(var(--reel-progress, 0))" }}
                />
            </div>
        </header>
    );
}
