import { Link, usePage } from "@inertiajs/react";
import { ArrowLeft } from "lucide-react";
import BrandLogo from "@/components/all/brand-logo";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { home } from "@/routes";
import { home as catalogue } from "@/routes/catalogue";

const GHOST_CLASS =
    "text-foreground/80 hover:bg-foreground/5 hover:text-foreground";

/**
 * The auth pages' header, sized and styled like the welcome page header: a
 * white bar with the full-colour logo above the black film-strip page.
 */
export default function AuthHeader() {
    const { name } = usePage().props;

    return (
        <header className="animate-in border-b border-yellow-200 bg-white px-4 text-foreground duration-700 fill-mode-both fade-in sm:px-6 lg:px-10">
            <div className="flex h-14 items-center justify-between gap-3 pt-2 lg:h-17 lg:gap-4 ">
                <div className="flex min-w-0 items-center gap-4">
                    <Link
                        href={home()}
                        className="group flex items-center font-semibold"
                    >
                        <BrandLogo
                            variant="compact"
                            alt={name}
                            className="h-8 transition-transform duration-300 group-hover:-translate-y-0.5"
                        />
                    </Link>
                    <span className="hidden items-center gap-2 border-l border-foreground/15 pl-4 font-mono text-[11px] tracking-[0.2em] text-foreground/50 sm:flex">
                        <span className="relative flex size-2">
                            <span className="absolute inset-0 animate-ping rounded-full bg-emerald-400/70" />
                            <span className="relative size-2 rounded-full bg-emerald-400" />
                        </span>
                        ARCHIVE ONLINE
                    </span>
                </div>

                <div className="flex shrink-0 items-center gap-1 sm:gap-2">
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className={cn(GHOST_CLASS, "hidden sm:inline-flex")}
                    >
                        <Link href={catalogue()}>Catalogue</Link>
                    </Button>
                    <Button
                        asChild
                        variant="outline"
                        className="group h-9 gap-2 rounded-full border-foreground/15 bg-white py-1 pr-4 pl-1 text-foreground shadow-none transition-[color,border-color,box-shadow] hover:border-foreground/30 hover:bg-white hover:text-foreground hover:shadow-sm"
                    >
                        <Link href={home()}>
                            <span className="flex size-7 items-center justify-center rounded-full bg-foreground/5 transition-colors duration-300 group-hover:bg-tertiary group-hover:text-tertiary-foreground">
                                <ArrowLeft className="size-3.5 transition-transform duration-300 group-hover:-translate-x-0.5" />
                            </span>
                            <span className="sm:hidden">Home</span>
                            <span className="hidden sm:inline">
                                Back to home
                            </span>
                        </Link>
                    </Button>
                </div>
            </div>
        </header>
    );
}
